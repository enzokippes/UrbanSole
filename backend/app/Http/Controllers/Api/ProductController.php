<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\N8nWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(
        protected N8nWebhookService $n8nWebhook
    ) {}

    /**
     * Query Builder con filtros dinámicos multicriterio (categoría, talles con stock, colores JSON, rango de precios y ordenamiento).
     */
    public function index(Request $request)
    {
        $query = Product::with('variants');

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

        // Append available sizes/colors to each product
        $products->getCollection()->transform(function ($product) {
            $product->available_sizes = $product->availableSizes();
            $product->available_colors = $product->availableColors();
            return $product;
        });

        return response()->json($products);
    }

    public function show($id)
    {
        $query = Product::with('variants');
        $product = is_numeric($id)
            ? $query->findOrFail($id)
            : $query->where('slug', $id)->firstOrFail();

        $product->available_sizes = $product->availableSizes();
        $product->available_colors = $product->availableColors();

        return response()->json($product);
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

        // Semana 15: Disparar webhook a n8n cuando se publica un nuevo producto 3D
        $this->n8nWebhook->dispatch('product.created', [
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

        // Semana 15: Disparar webhook a n8n al actualizar un producto
        $this->n8nWebhook->dispatch('product.updated', [
            'product_id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'price' => $product->price,
            'model_3d_url' => $product->model_3d_url,
        ], $request->user()?->email);

        // Si alguna variante tiene stock crítico (<= 3), emitir evento stock.low a n8n
        $lowStockVariants = $product->variants->where('stock', '<=', 3);
        if ($lowStockVariants->isNotEmpty()) {
            $this->n8nWebhook->dispatch('stock.low', [
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

        // Semana 15: Disparar webhook a n8n al eliminar un producto
        $this->n8nWebhook->dispatch('product.deleted', $deletedData, $request->user()?->email);

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
        return response()->json([
            'models' => [
                ['url' => '/models/jordan-chicago.glb', 'label' => 'Air Jordan 1 High OG "Chicago" (Rojo/Blanco/Negro)', 'category' => 'Hombre'],
                ['url' => '/models/jordan-bred.glb', 'label' => 'Air Jordan Retro "Gym Bred" (Rojo/Negro)', 'category' => 'Hombre'],
                ['url' => '/models/jordan-royal.glb', 'label' => 'Air Jordan 1 High "Black Toe Red" (Blanco/Negro/Rojo)', 'category' => 'Niño'],
                ['url' => '/models/af1-mocha.glb', 'label' => 'Nike Air Force 1 Low "Carhartt Mocha" (Marrón/Blanco)', 'category' => 'Hombre'],
                ['url' => '/models/velocity-midnight.glb', 'label' => 'Nike Air Max 90 / Velocity "Hyper Royal" (Azul/Blanco)', 'category' => 'Hombre'],
                ['url' => '/models/velocity-street.glb', 'label' => 'Nike Air Velocity "Crimson Street" (Negro/Rojo)', 'category' => 'Hombre'],
                ['url' => '/models/nb-olive.glb', 'label' => 'New Balance 247 "Olive Tactical" (Verde Oliva/Negro)', 'category' => 'Hombre'],
                ['url' => '/models/af1-pastel-shadow.glb', 'label' => 'Nike Air Force 1 Shadow "Pastel Dream" (Rosa/Lila/Amarillo)', 'category' => 'Mujer'],
                ['url' => '/models/velocity-beach.glb', 'label' => 'Nike Air Velocity "Rose Quartz" (Rosa Coral/Gris)', 'category' => 'Mujer'],
                ['url' => '/models/jordan-smoke-grey.glb', 'label' => 'Air Jordan 1 High "Ice White & Chrome" (Blanco/Gris)', 'category' => 'Mujer'],
                ['url' => '/models/jordan-atmosphere-pink.glb', 'label' => 'Air Jordan 1 High "Atmosphere Rose" (Rosa/Blanco)', 'category' => 'Mujer'],
                ['url' => '/models/sunset-chunky.glb', 'label' => 'UrbanSole RS-X "Sunset Prism" (Multicolor)', 'category' => 'Mujer'],
                ['url' => '/models/airmax-orange.glb', 'label' => 'Nike Air Max 1 "Citrus Orange" (Blanco/Naranja)', 'category' => 'Niño'],
                ['url' => '/models/vans-burgundy.glb', 'label' => 'Vans Old Skool "Skate Burgundy" (Bordó/Blanco)', 'category' => 'Niño'],
                ['url' => '/models/runner-grey-volt.glb', 'label' => 'Adidas Deerupt "Cyber Grid Volt" (Gris/Verde Neón)', 'category' => 'Niño'],
                ['url' => '/models/jordan-kids-sky.glb', 'label' => 'Air Jordan 1 Kids "University Sky" (Celeste/Blanco)', 'category' => 'Niño'],
                ['url' => '/models/rec-shoe.glb', 'label' => 'Urban Court Retro "Vintage Two-Tone"', 'category' => 'Niño'],
            ],
        ]);
    }
}
