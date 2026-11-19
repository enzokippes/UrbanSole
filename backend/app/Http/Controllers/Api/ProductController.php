<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(
        protected NotificationService $notifications
    ) {}

    /**
     * Query Builder con filtros dinámicos multicriterio (categoría, talles con stock, colores JSON, rango de precios y ordenamiento).
     */
    public function index(Request $request)
    {
        $query = Product::with('variants')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if (!$request->boolean('include_inactive')) {
            $query->active();
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Filtro por categoria
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filtro destacados
        if ($request->filled('featured')) {
            $query->featured();
        }

        // Filtro por color (JSON contains o coincidencia parcial sobre lista separada por comas)
        if ($request->filled('color')) {
            $colors = explode(',', $request->color);
            $query->where(function ($q) use ($colors) {
                foreach ($colors as $color) {
                    $trimmed = trim($color);
                    $q->orWhereJsonContains('colors', $trimmed)
                      ->orWhere('colors', 'like', '%' . $trimmed . '%');
                }
            });
        }

        // Filtro por talle sobre variantes con stock disponible
        if ($request->filled('size')) {
            $sizes = explode(',', $request->size);
            $query->whereHas('variants', function ($q) use ($sizes) {
                $q->whereIn('size', $sizes)->where('stock', '>', 0);
            });
        }

        // Filtro por rango de precios (min y max)
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Ordenamiento con whitelist estricta para evitar inyeccion SQL
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['price', 'name', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $products = $query->paginate($request->get('per_page', 12));

        // Append available sizes/colors & review summary to each product
        $products->getCollection()->transform(function ($product) {
            $product->available_sizes = $product->availableSizes();
            $product->available_colors = $product->availableColors();
            $product->reviews_avg_rating = $product->reviews_avg_rating !== null ? round((float) $product->reviews_avg_rating, 1) : null;
            $product->reviews_count = (int) ($product->reviews_count ?? 0);
            return $product;
        });

        return response()->json($products);
    }

    private function userReviewEligibility(?User $user, Product $product): string
    {
        if (! $user) {
            return 'guest';
        }

        if (Review::where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            return 'already_reviewed';
        }

        $variantIds = $product->variants->pluck('id');

        $hasDelivered = OrderItem::whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('status', 'delivered'))
            ->where(function ($q) use ($variantIds, $product) {
                $q->whereIn('product_variant_id', $variantIds)
                    ->orWhere('product_name', $product->name);
            })
            ->exists();

        if ($hasDelivered) {
            return 'can_review';
        }

        $hasInProgress = OrderItem::whereHas('order', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', ['pending', 'confirmed', 'shipped']))
            ->where(function ($q) use ($variantIds, $product) {
                $q->whereIn('product_variant_id', $variantIds)
                    ->orWhere('product_name', $product->name);
            })
            ->exists();

        return $hasInProgress ? 'pending_delivery' : 'no_purchase';
    }

    public function show($id)
    {
        $query = Product::with([
            'variants',
            'reviews' => fn ($q) => $q->latest('id'),
        ])->withCount('reviews')->withAvg('reviews', 'rating');

        $product = is_numeric($id)
            ? $query->findOrFail($id)
            : $query->where('slug', $id)->firstOrFail();

        $user = auth('sanctum')->user();
        $eligibility = $this->userReviewEligibility($user instanceof User ? $user : null, $product);

        $product->available_sizes = $product->availableSizes();
        $product->available_colors = $product->availableColors();
        $product->reviews_avg_rating = $product->reviews_avg_rating !== null ? round((float) $product->reviews_avg_rating, 1) : null;
        $product->reviews_count = (int) ($product->reviews_count ?? 0);
        $product->can_review = $eligibility === 'can_review';
        $product->review_eligibility = $eligibility;

        return response()->json($product);
    }

    public function storeReview(Request $request, int $id)
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:3|max:1000',
        ]);

        $product = Product::with('variants')->findOrFail($id);
        $user = $request->user();
        $eligibility = $this->userReviewEligibility($user, $product);

        if ($eligibility === 'already_reviewed') {
            return response()->json([
                'message' => 'Ya publicaste tu reseña para esta zapatilla. Solo se permite una opinión por cliente.',
            ], 422);
        }

        if ($eligibility !== 'can_review') {
            return response()->json([
                'message' => 'Solo podés calificar y reseñar zapatillas que hayas comprado y cuyo pedido figure como Entregado.',
            ], 403);
        }

        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'author_name' => $user->name,
            'rating' => (int) $data['rating'],
            'comment' => trim($data['comment']),
            'verified_purchase' => true,
        ]);

        $avg = $product->reviews()->avg('rating');
        $count = $product->reviews()->count();

        return response()->json([
            'review' => $review,
            'reviews_avg_rating' => $avg !== null ? round((float) $avg, 1) : null,
            'reviews_count' => (int) $count,
            'can_review' => false,
            'review_eligibility' => 'already_reviewed',
            'message' => '¡Gracias! Tu reseña fue publicada.',
        ], 201);
    }

    public function destroyReview(Request $request, int $id, int $reviewId)
    {
        $review = Review::where('product_id', $id)->findOrFail($reviewId);

        if ($request->user()->id !== $review->user_id && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'No tenés permiso para eliminar esta reseña.'], 403);
        }

        $productId = $review->product_id;
        $review->delete();

        $avg = Review::where('product_id', $productId)->avg('rating');
        $count = Review::where('product_id', $productId)->count();
        $product = Product::with('variants')->findOrFail($productId);
        $eligibility = $this->userReviewEligibility($request->user(), $product);

        return response()->json([
            'deleted_id' => $reviewId,
            'reviews_avg_rating' => $avg !== null ? round((float) $avg, 1) : null,
            'reviews_count' => (int) $count,
            'can_review' => $eligibility === 'can_review',
            'review_eligibility' => $eligibility,
            'message' => 'Reseña eliminada correctamente.',
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::lower(Str::random(4));

        if (empty($validated['colors'])) {
            $validated['colors'] = ['Clásico (Blanco/Negro)'];
        }
        if (empty($validated['images'])) {
            $validated['images'] = ['/images/jordan-chicago.jpg'];
        }
        if (empty($validated['model_3d_url'])) {
            $validated['model_3d_url'] = '/models/jordan-chicago.glb';
        }

        $product = Product::create($validated);

        // Crear variantes por defecto según categoría
        $sizes = match($product->category) {
            'Hombre' => ['39', '40', '41', '42', '43', '44'],
            'Mujer' => ['36', '37', '38', '39', '40'],
            'Niño' => ['29', '30', '31', '32', '33', '34'],
            default => ['40', '41', '42'],
        };

        foreach ($product->colors as $color) {
            foreach ($sizes as $size) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'size' => $size,
                    'color' => $color,
                    'stock' => 8,
                ]);
            }
        }

        $product->load('variants');
        $product->available_sizes = $product->availableSizes();
        $product->available_colors = $product->availableColors();

        // Notificar publicación de nuevo calzado
        $this->notifications->dispatch('product.created', [
            'product_id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'price' => $product->price,
            'model_3d_url' => $product->model_3d_url,
        ], $request->user()?->email);

        return response()->json([
            'message' => 'Producto creado correctamente',
            'data' => $product,
        ], 201);
    }

    public function update(UpdateProductRequest $request, $id)
    {
        $product = is_numeric($id)
            ? Product::findOrFail($id)
            : Product::where('slug', $id)->firstOrFail();

        $validated = $request->validated();

        if (isset($validated['name']) && $validated['name'] !== $product->name) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . $product->id;
        }

        $product->update($validated);
        $product->load('variants');
        $product->available_sizes = $product->availableSizes();
        $product->available_colors = $product->availableColors();

        // Notificar actualización de producto
        $this->notifications->dispatch('product.updated', [
            'product_id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'price' => $product->price,
            'model_3d_url' => $product->model_3d_url,
        ], $request->user()?->email);

        // Si alguna variante tiene stock crítico (<= 3), emitir alerta de stock bajo
        $lowStockVariants = $product->variants->where('stock', '<=', 3);
        if ($lowStockVariants->isNotEmpty()) {
            $this->notifications->dispatch('stock.low', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'low_stock_variants_count' => $lowStockVariants->count(),
                'min_stock' => $lowStockVariants->min('stock'),
            ], $request->user()?->email);
        }

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'data' => $product,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $product = is_numeric($id)
            ? Product::findOrFail($id)
            : Product::where('slug', $id)->firstOrFail();

        $deletedData = [
            'product_id' => $product->id,
            'name' => $product->name,
            'category' => $product->category,
        ];

        $product->delete();

        // Notificar eliminación de producto
        $this->notifications->dispatch('product.deleted', $deletedData, $request->user()?->email);

        return response()->json([
            'message' => 'Producto eliminado correctamente',
        ]);
    }

    public function categories()
    {
        return response()->json([
            'categories' => ['Hombre', 'Mujer', 'Niño'],
        ]);
    }

    public function models3d()
    {
        $disk = Storage::disk('public');
        $uploadedModels = collect($disk->exists('uploads/models') ? $disk->files('uploads/models') : [])
            ->filter(fn (string $p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['glb', 'gltf'], true))
            ->map(function (string $path) {
                $file = basename($path);

                return [
                    'url' => url("/api/media/models/{$file}"),
                    'image' => '/images/jordan-chicago.jpg',
                    'label' => "Subido desde PC: {$file}",
                    'category' => 'Hombre',
                    'custom' => true,
                ];
            })
            ->reverse()
            ->values()
            ->all();

        $builtinModels = [
            ['url' => '/models/jordan-chicago.glb', 'image' => '/images/jordan-chicago.jpg', 'label' => 'Air Jordan 1 High OG "Chicago" (Rojo/Blanco/Negro)', 'category' => 'Hombre'],
            ['url' => '/models/jordan-bred.glb', 'image' => '/images/jordan-bred.jpg', 'label' => 'Air Jordan Retro "Gym Bred" (Rojo/Negro)', 'category' => 'Hombre'],
            ['url' => '/models/jordan-royal.glb', 'image' => '/images/jordan-royal.jpg', 'label' => 'Air Jordan 1 High "Black Toe Red" (Blanco/Negro/Rojo)', 'category' => 'Niño'],
            ['url' => '/models/af1-mocha.glb', 'image' => '/images/jordan-mocha.jpg', 'label' => 'Nike Air Force 1 Low "Carhartt Mocha" (Marrón/Blanco)', 'category' => 'Hombre'],
            ['url' => '/models/velocity-midnight.glb', 'image' => '/images/velocity-blue.jpg', 'label' => 'Nike Air Max 90 / Velocity "Hyper Royal" (Azul/Blanco)', 'category' => 'Hombre'],
            ['url' => '/models/velocity-street.glb', 'image' => '/images/jordan-bred.jpg', 'label' => 'Nike Air Velocity "Crimson Street" (Negro/Rojo)', 'category' => 'Hombre'],
            ['url' => '/models/nb-olive.glb', 'image' => '/images/velocity-black.jpg', 'label' => 'New Balance 247 "Olive Tactical" (Verde Oliva/Negro)', 'category' => 'Hombre'],
            ['url' => '/models/af1-pastel-shadow.glb', 'image' => '/images/precision-low.jpg', 'label' => 'Nike Air Force 1 Shadow "Pastel Dream" (Rosa/Lila/Amarillo)', 'category' => 'Mujer'],
            ['url' => '/models/velocity-beach.glb', 'image' => '/images/velocity-runner.jpg', 'label' => 'Nike Air Velocity "Rose Quartz" (Rosa Coral/Gris)', 'category' => 'Mujer'],
            ['url' => '/models/jordan-smoke-grey.glb', 'image' => '/images/jordan-smoke-grey.jpg', 'label' => 'Air Jordan 1 High "Ice White & Chrome" (Blanco/Gris)', 'category' => 'Mujer'],
            ['url' => '/models/jordan-atmosphere-pink.glb', 'image' => '/images/precision-low.jpg', 'label' => 'Air Jordan 1 High "Atmosphere Rose" (Rosa/Blanco)', 'category' => 'Mujer'],
            ['url' => '/models/sunset-chunky.glb', 'image' => '/images/jordan-sunset.jpg', 'label' => 'UrbanSole RS-X "Sunset Prism" (Multicolor)', 'category' => 'Mujer'],
            ['url' => '/models/airmax-orange.glb', 'image' => '/images/dunk-coast.jpg', 'label' => 'Nike Air Max 1 "Citrus Orange" (Blanco/Naranja)', 'category' => 'Niño'],
            ['url' => '/models/vans-burgundy.glb', 'image' => '/images/canvas-retro.jpg', 'label' => 'Vans Old Skool "Skate Burgundy" (Bordó/Blanco)', 'category' => 'Niño'],
            ['url' => '/models/runner-grey-volt.glb', 'image' => '/images/velocity-coral.jpg', 'label' => 'Adidas Deerupt "Cyber Grid Volt" (Gris/Verde Neón)', 'category' => 'Niño'],
            ['url' => '/models/jordan-kids-sky.glb', 'image' => '/images/velocity-blue.jpg', 'label' => 'Air Jordan 1 Kids "University Sky" (Celeste/Blanco)', 'category' => 'Niño'],
            ['url' => '/models/rec-shoe.glb', 'image' => '/images/jordan-sunset.jpg', 'label' => 'Urban Court Retro "Vintage Two-Tone"', 'category' => 'Niño'],
            ['url' => '/models/sneaker.glb', 'image' => '/images/velocity-black.jpg', 'label' => 'Urban Runner Classic Mesh (.glb)', 'category' => 'Hombre'],
            ['url' => '/models/shoe-draco.glb', 'image' => '/images/velocity-runner.jpg', 'label' => 'Sport Performance Draco (.glb)', 'category' => 'Mujer'],
            ['url' => '/models/jordan.glb', 'image' => '/images/jordan-chicago.jpg', 'label' => 'Jordan Classic Silhouette (.glb)', 'category' => 'Hombre'],
        ];

        return response()->json([
            'models' => array_merge($uploadedModels, $builtinModels),
        ]);
    }
}
