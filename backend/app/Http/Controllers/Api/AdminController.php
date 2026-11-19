<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OrderReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
        protected OrderReceiptService $receiptService
    ) {}

    public function stats()
    {
        $sales = Order::whereIn('status', ['confirmed', 'shipped', 'delivered']);
        $totalProducts = Product::count();
        $activeProducts = Product::where('active', true)->count();
        $models3dCount = Product::whereNotNull('model_3d_url')->count();
        $totalStock = ProductVariant::sum('stock');
        $pendingRevenue = (float) Order::where('status', 'pending')->sum('total');
        $unitsSold = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->sum('order_items.quantity');

        $byCategory = [
            'Hombre' => Product::where('category', 'Hombre')->count(),
            'Mujer' => Product::where('category', 'Mujer')->count(),
            'Niño' => Product::where('category', 'Niño')->count(),
        ];

        $topSellingRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('product_variants.product_id as product_id, order_items.product_name as product_name, SUM(order_items.quantity) as units_sold, SUM(order_items.quantity * order_items.unit_price) as revenue, COUNT(DISTINCT order_items.order_id) as orders_count')
            ->groupBy('product_variants.product_id', 'order_items.product_name')
            ->orderByDesc('units_sold')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $productIds = $topSellingRows->pluck('product_id')->filter()->unique()->values();
        $productsById = Product::whereIn('id', $productIds)
            ->get(['id', 'name', 'brand', 'category', 'images'])
            ->keyBy('id');

        $topSellingProducts = $topSellingRows->map(function ($row) use ($productsById) {
            $product = $row->product_id ? $productsById->get($row->product_id) : null;

            return [
                'product_id' => $row->product_id ? (int) $row->product_id : null,
                'product_name' => $row->product_name,
                'brand' => $product?->brand,
                'category' => $product?->category,
                'image' => $product?->images[0] ?? '/images/jordan-chicago.jpg',
                'units_sold' => (int) $row->units_sold,
                'revenue' => round((float) $row->revenue, 2),
                'orders_count' => (int) $row->orders_count,
            ];
        })->values();

        $topSizes = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.size as size, SUM(order_items.quantity) as units_sold')
            ->groupBy('order_items.size')
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'size' => (string) $row->size,
                'units_sold' => (int) $row->units_sold,
            ])
            ->values();

        $salesByCategoryRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('products.category as category, SUM(order_items.quantity) as units_sold, SUM(order_items.quantity * order_items.unit_price) as revenue')
            ->groupBy('products.category')
            ->get()
            ->keyBy('category');

        $salesByCategory = [];
        foreach (['Hombre', 'Mujer', 'Niño'] as $cat) {
            $row = $salesByCategoryRows->get($cat);
            $salesByCategory[$cat] = [
                'units_sold' => (int) ($row->units_sold ?? 0),
                'revenue' => round((float) ($row->revenue ?? 0), 2),
            ];
        }

        $lowStockItems = ProductVariant::with('product:id,name,brand,category,images,active')
            ->where('stock', '<=', 3)
            ->whereHas('product', fn ($q) => $q->where('active', true))
            ->orderBy('stock')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn ($v) => [
                'variant_id' => $v->id,
                'product_id' => $v->product_id,
                'product_name' => $v->product?->name ?? 'Producto',
                'category' => $v->product?->category,
                'image' => $v->product?->images[0] ?? '/images/jordan-chicago.jpg',
                'size' => $v->size,
                'color' => $v->color,
                'stock' => (int) $v->stock,
            ])
            ->values();

        $recentOrders = Order::with(['user:id,name,email', 'items:id,order_id,product_name,size,color,quantity'])
            ->withCount('items')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'user_order_number' => (int) ($o->user_order_number ?: $o->id),
                'customer_name' => $o->user?->name ?? $o->customer_name ?? $o->recipient,
                'customer_email' => $o->user?->email ?? $o->customer_email,
                'user_deleted' => $o->user_id === null,
                'status' => $o->status,
                'total' => round((float) $o->total, 2),
                'items_count' => (int) $o->items_count,
                'items_summary' => $o->items->map(fn ($item) => "{$item->quantity}x {$item->product_name} (Talle {$item->size})")->values(),
                'created_at' => $o->created_at?->toISOString(),
            ])
            ->values();

        return response()->json([
            'total_users' => User::count(),
            'customers_count' => User::where('role', 'customer')->count(),
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'models_3d_count' => $models3dCount,
            'total_stock' => (int) $totalStock,
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'pending_revenue' => round($pendingRevenue, 2),
            'units_sold' => $unitsSold,
            'sales_total' => (clone $sales)->sum('total') ?? 0,
            'average_order' => (clone $sales)->avg('total') ?? 0,
            'low_stock_variants' => ProductVariant::where('stock', '<=', 3)->count(),
            'orders_by_status' => Order::selectRaw('status, COUNT(*) as count')->groupBy('status')->get(),
            'by_category' => $byCategory,
            'sales_by_category' => $salesByCategory,
            'top_selling_products' => $topSellingProducts,
            'top_sizes' => $topSizes,
            'low_stock_items' => $lowStockItems,
            'recent_orders' => $recentOrders,
            'n8n_config' => [
                'webhook_url' => config('services.n8n.webhook_url'),
                'enabled' => (bool) config('services.n8n.enabled'),
                'notify_email' => config('services.n8n.notify_email'),
            ],
            'message' => 'Admin stats access granted',
        ]);
    }

    public function products(Request $request)
    {
        $query = Product::with('variants')->latest('id');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if (in_array($request->query('category'), ['Hombre', 'Mujer', 'Niño'], true)) {
            $query->where('category', $request->query('category'));
        }

        return $query->paginate(50);
    }

    public function storeProduct(Request $request)
    {
        return $this->saveProduct($request, null);
    }

    public function updateProduct(Request $request, int $id)
    {
        return $this->saveProduct($request, $id);
    }

    private function defaultImageForModel(?string $modelUrl): string
    {
        return match ($modelUrl) {
            '/models/jordan-chicago.glb' => '/images/jordan-chicago.jpg',
            '/models/jordan-bred.glb' => '/images/jordan-bred.jpg',
            '/models/jordan-royal.glb' => '/images/jordan-royal.jpg',
            '/models/af1-mocha.glb' => '/images/jordan-mocha.jpg',
            '/models/velocity-midnight.glb' => '/images/velocity-blue.jpg',
            '/models/velocity-street.glb' => '/images/jordan-bred.jpg',
            '/models/nb-olive.glb' => '/images/velocity-black.jpg',
            '/models/af1-pastel-shadow.glb' => '/images/precision-low.jpg',
            '/models/velocity-beach.glb' => '/images/velocity-runner.jpg',
            '/models/jordan-smoke-grey.glb' => '/images/jordan-smoke-grey.jpg',
            '/models/jordan-atmosphere-pink.glb' => '/images/precision-low.jpg',
            '/models/sunset-chunky.glb' => '/images/jordan-sunset.jpg',
            '/models/airmax-orange.glb' => '/images/dunk-coast.jpg',
            '/models/vans-burgundy.glb' => '/images/canvas-retro.jpg',
            '/models/runner-grey-volt.glb' => '/images/velocity-coral.jpg',
            '/models/jordan-kids-sky.glb' => '/images/velocity-blue.jpg',
            '/models/rec-shoe.glb' => '/images/jordan-sunset.jpg',
            default => '/images/jordan-chicago.jpg',
        };
    }

    private function saveProduct(Request $request, ?int $id)
    {
        $req = $id ? 'sometimes|required' : 'required';
        $reqRules = $id ? ['sometimes', 'required'] : ['required'];
        $data = $request->validate([
            'name' => "{$req}|string|max:255",
            'brand' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => [...$reqRules, Rule::in(['Hombre', 'Mujer', 'Niño'])],
            'price' => "{$req}|numeric|min:0.01|max:99999999.99",
            'original_price' => 'nullable|numeric|min:0|max:99999999.99',
            'active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'string|max:2048',
            'color_images' => 'nullable|array',
            'color_images.*' => 'string|max:2048',
            'colors' => 'nullable|array',
            'colors.*' => 'string|max:255',
            'model_3d_url' => 'nullable|string|max:2048',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
            'variants' => 'nullable|array|min:1|max:200',
            'variants.*.id' => 'nullable|integer',
            'variants.*.size' => 'required|string|max:255',
            'variants.*.color' => 'required|string|max:255',
            'variants.*.stock' => 'required|integer|min:0|max:1000000',
        ]);

        if (! $id) {
            $data['brand'] = $data['brand'] ?? 'Nike';
            $data['description'] = $data['description'] ?? 'Zapatilla urbana de diseño exclusivo.';
            $data['active'] = $data['active'] ?? true;
            $data['featured'] = $data['featured'] ?? false;
            if (! array_key_exists('model_3d_url', $data)) {
                $data['model_3d_url'] = '/models/jordan-chicago.glb';
            }
            if (empty($data['images'])) {
                $data['images'] = [$this->defaultImageForModel($data['model_3d_url'])];
            }
        }

        $product = DB::transaction(function () use ($data, $id, $request) {
            $product = $id ? Product::whereKey($id)->lockForUpdate()->firstOrFail() : new Product;
            $previousModelUrl = $product->model_3d_url;
            $existing = $id ? $product->variants()->orderBy('id')->lockForUpdate()->get()->keyBy('id') : collect();
            $hasVariants = array_key_exists('variants', $data) && ! empty($data['variants']);

            if ($hasVariants) {
                $pairs = [];
                $retained = [];
                foreach ($data['variants'] as $variant) {
                    $pair = json_encode([$variant['size'], $variant['color']]);
                    if (isset($pairs[$pair])) {
                        throw ValidationException::withMessages(['variants' => 'Hay variantes repetidas.']);
                    }
                    $pairs[$pair] = true;
                    if (! empty($variant['id'])) {
                        if (! $existing->has($variant['id']) || in_array($variant['id'], $retained)) {
                            throw ValidationException::withMessages(['variants' => 'La variante no pertenece a este producto o está repetida.']);
                        }
                        $old = $existing->get($variant['id']);
                        if (($old->size !== $variant['size'] || $old->color !== $variant['color']) && OrderItem::where('product_variant_id', $old->id)->exists()) {
                            throw ValidationException::withMessages(['variants' => 'Una variante con pedidos conserva su talle y color. Creá una variante nueva.']);
                        }
                        $retained[] = $variant['id'];
                    }
                }
                $removed = $existing->keys()->diff($retained);
                if (OrderItem::whereIn('product_variant_id', $removed)->exists()) {
                    throw ValidationException::withMessages(['variants' => 'No se puede quitar una variante con pedidos. Conservála y ajustá su stock.']);
                }
            }

            $product->fill(collect($data)->except('variants')->all());
            if (! $id) {
                $product->slug = Str::slug($data['name']).'-'.Str::lower(Str::random(8));
            }
            if ($id && ! array_key_exists('images', $data) && array_key_exists('model_3d_url', $data) && $data['model_3d_url'] !== $previousModelUrl) {
                $product->images = [$this->defaultImageForModel($data['model_3d_url'])];
            }

            if ($hasVariants) {
                $product->colors = array_values(array_unique(array_column($data['variants'], 'color')));
            } elseif (! $id && empty($product->colors)) {
                $product->colors = [$request->input('colors.0', 'Clásico')];
            }

            $product->save();

            if ($hasVariants) {
                foreach ($data['variants'] as $variant) {
                    $values = collect($variant)->except('id')->all();
                    if (! empty($variant['id'])) {
                        $existing->get($variant['id'])->update($values);
                    } else {
                        $product->variants()->create($values);
                    }
                }
                $product->variants()->whereIn('id', $removed)->delete();
            } elseif (! $id) {
                $sizes = match ($product->category) {
                    'Hombre' => ['39', '40', '41', '42', '43', '44'],
                    'Mujer' => ['36', '37', '38', '39', '40'],
                    'Niño' => ['29', '30', '31', '32', '33', '34'],
                    default => ['40', '41', '42'],
                };
                $defaultColor = $product->colors[0] ?? 'Clásico';
                foreach ($sizes as $size) {
                    $product->variants()->create([
                        'size' => $size,
                        'color' => $defaultColor,
                        'stock' => 8,
                    ]);
                }
            }

            return $product->load('variants');
        }, 3);

        // Notificar alta / edición de producto
        $this->notifications->dispatch($id ? 'product.updated' : 'product.created', [
            'product_id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'price' => $product->price,
            'model_3d_url' => $product->model_3d_url,
        ], $request->user()?->email);

        if ($id) {
            $lowStockVariants = $product->variants->where('stock', '<=', 3);
            if ($lowStockVariants->isNotEmpty()) {
                $this->notifications->dispatch('stock.low', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'low_stock_variants_count' => $lowStockVariants->count(),
                    'min_stock' => $lowStockVariants->min('stock'),
                ], $request->user()?->email);
            }
        }

        return response()->json($product, $id ? 200 : 201);
    }

    public function destroyProduct(Request $request, int $id)
    {
        // Preserve purchased products and their history; remove from the public catalog.
        $product = DB::transaction(function () use ($id) {
            $product = Product::whereKey($id)->lockForUpdate()->firstOrFail();
            $product->update(['active' => false]);

            return $product;
        });

        $this->notifications->dispatch('product.deleted', [
            'product_id' => $product->id,
            'name' => $product->name,
            'category' => $product->category,
        ], $request->user()?->email);

        return response()->json(['message' => 'Producto desactivado.']);
    }

    public function orders(Request $request)
    {
        $query = Order::with(['items', 'user:id,name,email'])->latest('id');

        if (in_array($request->query('status'), ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'], true)) {
            $query->where('status', $request->query('status'));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $numericId = ltrim($search, '#');
            $query->where(function ($q) use ($search, $numericId) {
                if (ctype_digit($numericId)) {
                    $q->orWhere('id', (int) $numericId)
                        ->orWhere('user_order_number', (int) $numericId);
                }
                $q->orWhere('recipient', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('product_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate(50);
    }

    public function orderReceipt(int $id)
    {
        $order = Order::with(['items', 'user:id,name,email'])->findOrFail($id);
        $pdf = $this->receiptService->renderPdfContent($order);
        $filename = $this->receiptService->filename($order);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function users(Request $request)
    {
        $query = User::query()
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (in_array($request->query('role'), ['admin', 'customer'], true)) {
            $query->where('role', $request->query('role'));
        }

        if ($request->query('status') === 'banned') {
            $query->whereNotNull('banned_at');
        } elseif ($request->query('status') === 'active') {
            $query->whereNull('banned_at');
        }

        return $query->latest('id')->paginate(20);
    }

    public function userOrders(int $id)
    {
        $user = User::withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->findOrFail($id);

        $orders = $user->orders()->with('items')->latest('id')->paginate(20);

        return response()->json([
            'user' => $user,
            'orders' => $orders,
        ]);
    }

    public function updateUserRole(Request $request, int $id)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['customer', 'admin'])],
        ]);

        $target = User::findOrFail($id);

        if ($target->id === $request->user()->id && $data['role'] !== 'admin') {
            throw ValidationException::withMessages([
                'role' => 'No podés quitarte tu propio rol de administrador.',
            ]);
        }

        $target->update(['role' => $data['role']]);
        $target->loadCount('orders');
        $target->loadSum(['orders as total_spent' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total');

        return response()->json([
            'user' => $target,
            'message' => $data['role'] === 'admin'
                ? "Se otorgó rol de administrador a {$target->name}."
                : "Se cambió a {$target->name} al rol de cliente.",
        ]);
    }

    public function toggleUserBan(Request $request, int $id)
    {
        $data = $request->validate([
            'banned' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $target = User::findOrFail($id);

        if ($target->id === $request->user()->id && $data['banned']) {
            throw ValidationException::withMessages([
                'banned' => 'No podés suspender tu propia cuenta.',
            ]);
        }

        if ($data['banned']) {
            $reason = trim((string) ($data['reason'] ?? ''));
            $target->update([
                'banned_at' => now(),
                'ban_reason' => $reason !== '' ? $reason : 'Suspendida por administración',
            ]);
            $target->tokens()->delete();
        } else {
            $target->update([
                'banned_at' => null,
                'ban_reason' => null,
            ]);
        }

        $target->loadCount('orders');
        $target->loadSum(['orders as total_spent' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total');

        return response()->json([
            'user' => $target,
            'message' => $data['banned']
                ? "Usuario {$target->name} suspendido correctamente."
                : "Usuario {$target->name} reactivado correctamente.",
        ]);
    }

    public function destroyUser(Request $request, int $id)
    {
        $target = User::findOrFail($id);

        if ($target->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'No podés eliminar tu propia cuenta de administrador.',
            ]);
        }

        $targetName = $target->name;
        $targetEmail = $target->email;

        $preservedOrders = DB::transaction(function () use ($target, $targetName, $targetEmail) {
            $count = Order::where('user_id', $target->id)->count();

            if ($count > 0) {
                Order::where('user_id', $target->id)->update([
                    'customer_name' => $targetName,
                    'customer_email' => $targetEmail,
                    'user_id' => null,
                ]);
            }

            $target->tokens()->delete();
            $target->delete();

            return $count;
        }, 3);

        $ordersNote = $preservedOrders > 0
            ? " Se conservaron sus {$preservedOrders} pedido(s) en el historial de ventas."
            : '';

        return response()->json([
            'deleted_user_id' => $id,
            'preserved_orders_count' => $preservedOrders,
            'message' => "Cuenta de {$targetName} eliminada permanentemente.{$ordersNote}",
        ]);
    }

    public function updateOrder(Request $request, int $id)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])]]);
        $statusChanged = false;

        $order = DB::transaction(function () use ($data, $id, &$statusChanged) {
            $statusChanged = false;
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            $transitions = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => [], 'cancelled' => []];
            if ($data['status'] === $order->status) {
                return $order->load(['items', 'user:id,name,email']);
            }
            if (! in_array($data['status'], $transitions[$order->status])) {
                throw ValidationException::withMessages(['status' => 'El cambio de estado no está permitido.']);
            }
            if ($data['status'] === 'cancelled') {
                $items = $order->items()->orderBy('product_variant_id')->get();
                foreach ($items as $item) {
                    if ($item->product_variant_id) {
                        ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first()?->increment('stock', $item->quantity);
                    }
                }
            }
            $updateFields = $data;
            if ($data['status'] === 'delivered' && $order->payment_status !== 'paid') {
                $updateFields['payment_status'] = 'paid';
            }
            $order->update($updateFields);
            $statusChanged = true;

            return $order->load(['items', 'user:id,name,email']);
        }, 3);

        if ($statusChanged) {
            $customerEmail = $order->user?->email ?? $order->customer_email;
            if (! empty($customerEmail)) {
                $this->notifications->dispatch('order.status_updated', [
                    'order_id' => $order->user_order_number ?: $order->id,
                    'internal_order_id' => $order->id,
                    'status' => $order->status,
                    'status_label' => $this->receiptService->statusLabel($order->status),
                    'payment_label' => $this->receiptService->paymentMethodLabel($order->payment_method),
                    'delivery_label' => $this->receiptService->deliveryMethodLabel($order->delivery_method),
                    'customer_name' => $order->user?->name ?? $order->customer_name ?? $order->recipient,
                    'email' => $customerEmail,
                    'recipient' => $order->recipient,
                    'address' => $order->address,
                    'city' => $order->city,
                    'postal_code' => $order->postal_code,
                    'total' => (float) $order->total,
                    'items' => $this->receiptService->buildItemsPayload($order),
                ], $request->user()?->email ?? 'admin@urbansole.com', $customerEmail);
            }
        }

        return response()->json($order);
    }

    public function downloadOrderPaymentProof(int $id)
    {
        $order = Order::findOrFail($id);

        if (empty($order->payment_proof_path) || ! Storage::disk('local')->exists($order->payment_proof_path)) {
            abort(404, 'Este pedido no tiene comprobante adjunto.');
        }

        $ext = strtolower(pathinfo($order->payment_proof_path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
        $num = $order->user_order_number ?: $order->id;

        return response(Storage::disk('local')->get($order->payment_proof_path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "inline; filename=\"comprobante-pedido-{$num}.{$ext}\"",
        ]);
    }

    public function verifyOrderPayment(Request $request, int $id)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:200'],
            'auto_confirm' => ['nullable', 'boolean'],
        ]);

        $order = Order::with(['items', 'user:id,name,email'])->findOrFail($id);

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'No se puede verificar el pago de un pedido cancelado.'], 422);
        }

        if ($data['action'] === 'approve') {
            $updates = ['payment_status' => 'paid'];
            $autoConfirm = $data['auto_confirm'] ?? true;
            if ($autoConfirm && $order->status === 'pending') {
                $updates['status'] = 'confirmed';
            }
            $order->update($updates);
            $order->refresh()->load(['items', 'user:id,name,email']);

            $note = 'Tu transferencia/pago fue verificado y acreditado correctamente.';
            $msg = 'Pago acreditado correctamente.' . ($order->status === 'confirmed' ? ' El pedido pasó a Confirmado.' : '');
        } else {
            if (! empty($order->payment_proof_path) && Storage::disk('local')->exists($order->payment_proof_path)) {
                Storage::disk('local')->delete($order->payment_proof_path);
            }
            $order->update([
                'payment_status' => 'pending',
                'payment_proof_path' => null,
                'payment_reference' => null,
                'payment_submitted_at' => null,
            ]);
            $order->refresh()->load(['items', 'user:id,name,email']);

            $reason = trim((string) ($data['reason'] ?? ''));
            $note = 'El comprobante enviado no pudo validarse' . ($reason !== '' ? " ({$reason})" : '') . '. Por favor subí uno nuevo desde Mis pedidos.';
            $msg = 'Comprobante rechazado. El cliente podrá subir otro comprobante.';
        }

        $customerEmail = $order->user?->email ?? $order->customer_email;
        if (! empty($customerEmail)) {
            $this->notifications->dispatch('order.status_updated', [
                'order_id' => $order->user_order_number ?: $order->id,
                'internal_order_id' => $order->id,
                'status' => $order->status,
                'status_label' => $data['action'] === 'approve'
                    ? $this->receiptService->statusLabel($order->status)
                    : 'Comprobante observado',
                'payment_label' => $this->receiptService->paymentMethodLabel($order->payment_method),
                'delivery_label' => $this->receiptService->deliveryMethodLabel($order->delivery_method),
                'note' => $note,
                'customer_name' => $order->user?->name ?? $order->customer_name ?? $order->recipient,
                'email' => $customerEmail,
                'recipient' => $order->recipient,
                'address' => $order->address,
                'city' => $order->city,
                'postal_code' => $order->postal_code,
                'total' => (float) $order->total,
                'items' => $this->receiptService->buildItemsPayload($order),
            ], $request->user()?->email ?? 'admin@urbansole.com', $customerEmail);
        }

        return response()->json([
            'order' => $order,
            'message' => $msg,
        ]);
    }

    public function restockVariant(Request $request, int $id)
    {
        $data = $request->validate([
            'add_stock' => 'required|integer|min:1|max:10000',
        ]);

        $variant = ProductVariant::with('product:id,name')->findOrFail($id);
        $variant->increment('stock', (int) $data['add_stock']);
        $variant->refresh();

        return response()->json([
            'variant' => $variant,
            'new_stock' => (int) $variant->stock,
            'message' => "Stock actualizado a {$variant->stock} pares para {$variant->product?->name} (Talle {$variant->size} · {$variant->color}).",
        ]);
    }

    public function uploadImage(Request $request)
    {
        $allowedImages = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
        $allowedModels = ['glb', 'gltf'];

        // 1. Subida binaria directa (application/octet-stream) que evita el límite upload_max_filesize=2M de php.ini
        $rawFilename = urldecode((string) $request->header('X-File-Name', ''));
        if ($rawFilename !== '' && ! $request->hasFile('image') && ! $request->hasFile('file')) {
            $ext = strtolower(pathinfo($rawFilename, PATHINFO_EXTENSION));
            $baseName = Str::slug(pathinfo($rawFilename, PATHINFO_FILENAME)) ?: 'asset';
            $assetType = strtolower((string) $request->header('X-Asset-Type', ''));

            if (! in_array($ext, array_merge($allowedImages, $allowedModels), true)) {
                return response()->json([
                    'message' => 'Formato no permitido. Usá JPG, PNG, WEBP, AVIF para fotos o GLB/GLTF para modelos 3D.',
                ], 422);
            }

            if ($assetType === 'model' && ! in_array($ext, $allowedModels, true)) {
                return response()->json(['message' => 'El modelo 3D debe ser un archivo .glb o .gltf.'], 422);
            }
            if ($assetType === 'image' && ! in_array($ext, $allowedImages, true)) {
                return response()->json(['message' => 'La imagen debe ser .jpg, .png, .webp o .avif.'], 422);
            }

            $binary = $request->getContent();
            $sizeBytes = strlen($binary);
            $isModel = in_array($ext, $allowedModels, true);
            $maxBytes = $isModel ? 50 * 1024 * 1024 : 15 * 1024 * 1024;

            if ($sizeBytes === 0) {
                return response()->json(['message' => 'El archivo seleccionado está vacío.'], 422);
            }
            if ($sizeBytes > $maxBytes) {
                return response()->json(['message' => 'El archivo supera el tamaño máximo permitido.'], 422);
            }
            if ($ext === 'glb' && substr($binary, 0, 4) !== 'glTF') {
                return response()->json(['message' => 'El archivo .glb no tiene una cabecera glTF binaria válida.'], 422);
            }

            $folder = $isModel ? 'models' : 'images';
            $filename = "{$baseName}-" . Str::lower(Str::random(6)) . ".{$ext}";
            $storagePath = "uploads/{$folder}/{$filename}";

            Storage::disk('public')->put($storagePath, $binary);

            return response()->json([
                'url' => url("/api/media/{$folder}/{$filename}"),
                'path' => $storagePath,
                'type' => $isModel ? 'model' : 'image',
                'name' => "{$baseName}.{$ext}",
                'message' => $isModel ? 'Modelo 3D (.glb) subido correctamente.' : 'Imagen subida correctamente.',
            ], 201);
        }

        // 2. Subida multipart estándar (image o file)
        $uploaded = $request->file('image') ?? $request->file('file');
        if (! $uploaded) {
            return response()->json(['message' => 'No se recibió ningún archivo o supera el límite de subida.'], 422);
        }

        $ext = strtolower($uploaded->getClientOriginalExtension() ?: $uploaded->guessExtension() ?: 'jpg');
        if (! in_array($ext, array_merge($allowedImages, $allowedModels), true)) {
            return response()->json(['message' => 'Formato de archivo no soportado.'], 422);
        }

        $isModel = in_array($ext, $allowedModels, true);
        $folder = $isModel ? 'models' : 'images';
        $baseName = Str::slug(pathinfo($uploaded->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'asset';
        $filename = "{$baseName}-" . Str::lower(Str::random(6)) . ".{$ext}";
        $storagePath = "uploads/{$folder}/{$filename}";

        Storage::disk('public')->put($storagePath, file_get_contents($uploaded->getRealPath()));

        return response()->json([
            'url' => url("/api/media/{$folder}/{$filename}"),
            'path' => $storagePath,
            'type' => $isModel ? 'model' : 'image',
            'name' => $uploaded->getClientOriginalName(),
            'message' => $isModel ? 'Modelo 3D subido correctamente.' : 'Imagen subida correctamente.',
        ], 201);
    }

    public function serveMedia(string $folder, string $filename)
    {
        if (! in_array($folder, ['images', 'models', 'products'], true)) {
            abort(404);
        }

        $safeFile = basename($filename);
        $disk = Storage::disk('public');
        $candidates = [
            "uploads/{$folder}/{$safeFile}",
            "{$folder}/{$safeFile}",
            "products/{$safeFile}",
        ];

        $foundPath = null;
        foreach ($candidates as $candidate) {
            if ($disk->exists($candidate)) {
                $foundPath = $candidate;
                break;
            }
        }

        if (! $foundPath) {
            abort(404);
        }

        $ext = strtolower(pathinfo($safeFile, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'glb' => 'model/gltf-binary',
            'gltf' => 'model/gltf+json',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };

        return response($disk->get($foundPath), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function mediaLibrary()
    {
        $disk = Storage::disk('public');

        $uploadedImages = collect($disk->exists('uploads/images') ? $disk->files('uploads/images') : [])
            ->map(function (string $path) {
                $file = basename($path);

                return [
                    'url' => url("/api/media/images/{$file}"),
                    'label' => $file,
                    'source' => 'Subida desde tu PC',
                ];
            })
            ->reverse()
            ->values();

        $builtinImages = collect([
            ['url' => '/images/jordan-chicago.jpg', 'label' => 'Jordan 1 High Chicago'],
            ['url' => '/images/jordan-bred.jpg', 'label' => 'Jordan Retro Bred'],
            ['url' => '/images/jordan-royal.jpg', 'label' => 'Jordan 1 Black Toe / Royal'],
            ['url' => '/images/jordan-mocha.jpg', 'label' => 'Air Force 1 / Jordan Mocha'],
            ['url' => '/images/jordan-smoke-grey.jpg', 'label' => 'Jordan 1 Ice White & Grey'],
            ['url' => '/images/jordan-sunset.jpg', 'label' => 'UrbanSole Sunset Prism'],
            ['url' => '/images/velocity-blue.jpg', 'label' => 'Velocity Hyper Royal Blue'],
            ['url' => '/images/velocity-black.jpg', 'label' => 'New Balance / Velocity Tactical'],
            ['url' => '/images/velocity-coral.jpg', 'label' => 'Runner Cyber Volt / Coral'],
            ['url' => '/images/velocity-runner.jpg', 'label' => 'Velocity Rose Quartz'],
            ['url' => '/images/precision-low.jpg', 'label' => 'Air Force 1 Pastel Shadow'],
            ['url' => '/images/dunk-coast.jpg', 'label' => 'Air Max Citrus Orange / Coast'],
            ['url' => '/images/canvas-retro.jpg', 'label' => 'Vans Old Skool Burgundy'],
        ])->map(fn ($i) => array_merge($i, ['source' => 'Catálogo Base']));

        return response()->json([
            'images' => $uploadedImages->concat($builtinImages)->values(),
        ]);
    }

    /**
     * Semana 15: Webhook / Automatización de eventos (integración real HTTP POST con n8n + Email).
     */
    public function triggerWebhook(Request $request)
    {
        $event = $request->input('event', 'catalog.3d.synced');
        $overrideEmail = $request->input('notify_email');

        $lowStockVariants = ProductVariant::with('product')->where('stock', '<=', 3)->get();

        $defaultData = match ($event) {
            'user.registered' => [
                'user_id' => $request->user()?->id ?? 1,
                'name' => $request->user()?->name ?? 'Enzo Kippes',
                'email' => $overrideEmail ?: ($request->user()?->email ?? config('services.n8n.notify_email')),
                'role' => 'customer',
            ],
            'order.created' => [
                'order_id' => 101,
                'customer_name' => $request->user()?->name ?? 'Cliente UrbanSole',
                'email' => $overrideEmail ?: ($request->user()?->email ?? config('services.n8n.notify_email')),
                'recipient' => 'Cliente UrbanSole',
                'address' => 'Av. Entre Ríos 123',
                'city' => 'Concordia',
                'postal_code' => '3200',
                'total' => 189.99,
                'items' => [
                    [
                        'product_name' => 'Air Jordan 1 High OG "Chicago"',
                        'size' => '42',
                        'color' => 'Rojo/Blanco',
                        'quantity' => 1,
                        'unit_price' => 189.99,
                        'subtotal' => 189.99,
                    ],
                ],
                'receipt_filename' => 'recibo-urbansole-pedido-101.pdf',
            ],
            'product.created' => [
                'product_id' => 1,
                'name' => 'Air Jordan 1 High OG "Chicago"',
                'brand' => 'Nike Jordan',
                'category' => 'Hombre',
                'price' => 189.99,
                'model_3d_url' => '/models/jordan-chicago.glb',
            ],
            'stock.low', 'inventory.low_stock.check' => [
                'product_name' => $lowStockVariants->first()?->product?->name ?? 'Air Jordan 1 High OG "Chicago"',
                'low_stock_variants_count' => $lowStockVariants->count(),
                'total_products' => Product::count(),
                'total_stock' => (int) ProductVariant::sum('stock'),
            ],
            default => [
                'total_products' => Product::count(),
                'models_3d_count' => Product::whereNotNull('model_3d_url')->count(),
                'total_stock' => (int) ProductVariant::sum('stock'),
                'status' => 'synchronized',
            ],
        };

        $data = array_merge($defaultData, (array) $request->input('data', []));

        if ($event === 'order.created' && empty($data['receipt_pdf_base64'])) {
            $latestOrder = Order::with(['items', 'user:id,name,email'])->latest('id')->first();
            if ($latestOrder) {
                $data['order_id'] = $latestOrder->user_order_number ?: $latestOrder->id;
                $data['customer_name'] = $latestOrder->user?->name ?? $latestOrder->recipient;
                $data['recipient'] = $latestOrder->recipient;
                $data['address'] = $latestOrder->address;
                $data['city'] = $latestOrder->city;
                $data['postal_code'] = $latestOrder->postal_code;
                $data['total'] = (float) $latestOrder->total;
                $data['items'] = $this->receiptService->buildItemsPayload($latestOrder);
                $data['receipt_filename'] = $this->receiptService->filename($latestOrder);
                try {
                    $data['receipt_pdf_base64'] = base64_encode($this->receiptService->renderPdfContent($latestOrder));
                } catch (\Throwable) {
                }
            }
        }

        $result = $this->notifications->dispatch(
            $event,
            $data,
            $request->user()?->email ?? 'admin@urbansole.com',
            $overrideEmail
        );

        return response()->json($result);
    }

    /**
     * Semana 8: Especificación OpenAPI 3.0 / Swagger interactiva con formularios RequestBody y Bearer Auth.
     */
    public function openApiSpec()
    {
        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'UrbanSole E-Commerce API',
                'version' => '2.0.0',
                'description' => "API REST de UrbanSole.\n\n**Autenticación:**\n1. Ejecutá `POST /auth/login` para obtener tu token.\n2. Hacé clic en **Authorize 🔒**, pegá el token y confirmá.\n3. Accedé a los endpoints protegidos de pedidos y administración.",
            ],
            'servers' => [
                ['url' => 'http://localhost:8000/api', 'description' => 'Servidor Local'],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Bearer Token',
                        'description' => 'Pegá aquí el token devuelto por POST /auth/login.',
                    ],
                ],
            ],
            'paths' => [
                '/auth/login' => [
                    'post' => [
                        'tags' => ['Autenticación'],
                        'summary' => 'Iniciar sesión y obtener token de acceso',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email', 'password'],
                                        'properties' => [
                                            'email' => ['type' => 'string', 'example' => 'admin@urbansole.com'],
                                            'password' => ['type' => 'string', 'example' => 'password'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'Login exitoso y token generado']],
                    ],
                ],
                '/auth/register' => [
                    'post' => [
                        'tags' => ['Autenticación'],
                        'summary' => 'Registrar nuevo usuario y enviar correo de bienvenida',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'email', 'password', 'password_confirmation'],
                                        'properties' => [
                                            'name' => ['type' => 'string', 'example' => 'Juan Pérez'],
                                            'email' => ['type' => 'string', 'example' => 'juan.perez@urbansole.com'],
                                            'password' => ['type' => 'string', 'example' => 'password123'],
                                            'password_confirmation' => ['type' => 'string', 'example' => 'password123'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['201' => ['description' => 'Usuario creado y token emitido']],
                    ],
                ],
                '/orders' => [
                    'get' => [
                        'tags' => ['Pedidos'],
                        'summary' => 'Historial de pedidos del cliente autenticado',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Lista paginada de pedidos']],
                    ],
                    'post' => [
                        'tags' => ['Pedidos'],
                        'summary' => 'Crear nueva orden de compra',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['201' => ['description' => 'Orden creada']],
                    ],
                ],
                '/products' => [
                    'get' => [
                        'tags' => ['Catálogo'],
                        'summary' => 'Listar productos con filtros y modelos 3D',
                        'parameters' => [
                            ['name' => 'category', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['Hombre', 'Mujer', 'Niño']]],
                            ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'size', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'color', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'min_price', 'in' => 'query', 'schema' => ['type' => 'number']],
                            ['name' => 'max_price', 'in' => 'query', 'schema' => ['type' => 'number']],
                            ['name' => 'sort_by', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['price', 'name', 'created_at']]],
                        ],
                        'responses' => ['200' => ['description' => 'Lista paginada de productos']],
                    ],
                ],
                '/admin/products' => [
                    'get' => [
                        'tags' => ['Administración'],
                        'summary' => 'Listar productos en administración',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Productos admin']],
                    ],
                    'post' => [
                        'tags' => ['Administración'],
                        'summary' => 'Crear nuevo producto',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['201' => ['description' => 'Producto creado']],
                    ],
                ],
                '/admin/orders' => [
                    'get' => [
                        'tags' => ['Administración'],
                        'summary' => 'Listar todas las órdenes de la tienda',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Lista de órdenes']],
                    ],
                ],
                '/admin/users' => [
                    'get' => [
                        'tags' => ['Administración'],
                        'summary' => 'Listar y gestionar usuarios registrados',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Lista de usuarios']],
                    ],
                ],
                '/admin/webhooks/n8n' => [
                    'post' => [
                        'tags' => ['Notificaciones'],
                        'summary' => 'Disparar notificación o webhook',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Notificación procesada']],
                    ],
                ],
            ],
        ]);
    }
}
