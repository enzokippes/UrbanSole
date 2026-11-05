<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\N8nWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        protected N8nWebhookService $n8nWebhook
    ) {}

    public function stats()
    {
        $sales = Order::whereIn('status', ['confirmed', 'shipped', 'delivered']);
        $totalProducts = Product::count();
        $activeProducts = Product::where('active', true)->count();
        $models3dCount = Product::whereNotNull('model_3d_url')->count();
        $totalStock = ProductVariant::sum('stock');

        $byCategory = [
            'Hombre' => Product::where('category', 'Hombre')->count(),
            'Mujer' => Product::where('category', 'Mujer')->count(),
            'Niño' => Product::where('category', 'Niño')->count(),
        ];

        return response()->json([
            'total_users' => User::count(),
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'models_3d_count' => $models3dCount,
            'total_stock' => (int) $totalStock,
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'sales_total' => (clone $sales)->sum('total') ?? 0,
            'average_order' => (clone $sales)->avg('total') ?? 0,
            'low_stock_variants' => ProductVariant::where('stock', '<=', 3)->count(),
            'orders_by_status' => Order::selectRaw('status, COUNT(*) as count')->groupBy('status')->get(),
            'by_category' => $byCategory,
            'n8n_config' => [
                'webhook_url' => config('services.n8n.webhook_url'),
                'enabled' => (bool) config('services.n8n.enabled'),
                'notify_email' => config('services.n8n.notify_email'),
            ],
            'message' => 'Admin stats access granted',
        ]);
    }

    public function products()
    {
        return Product::with('variants')->latest('id')->paginate(20);
    }

    public function storeProduct(Request $request)
    {
        return $this->saveProduct($request, null);
    }

    public function updateProduct(Request $request, int $id)
    {
        return $this->saveProduct($request, $id);
    }

    private function saveProduct(Request $request, ?int $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => ['required', Rule::in(['Hombre', 'Mujer', 'Niño'])],
            'price' => 'required|numeric|min:0.01|max:99999999.99',
            'original_price' => 'nullable|numeric|min:0|max:99999999.99',
            'active' => 'required|boolean',
            'featured' => 'required|boolean',
            'images' => 'nullable|array',
            'images.*' => 'string|max:2048',
            'color_images' => 'nullable|array',
            'color_images.*' => 'string|max:2048',
            'model_3d_url' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
            'variants' => 'required|array|min:1|max:200',
            'variants.*.id' => 'nullable|integer',
            'variants.*.size' => 'required|string|max:255',
            'variants.*.color' => 'required|string|max:255',
            'variants.*.stock' => 'required|integer|min:0|max:1000000',
        ]);

        $product = DB::transaction(function () use ($data, $id) {
            $product = $id ? Product::whereKey($id)->lockForUpdate()->firstOrFail() : new Product;
            $existing = $id ? $product->variants()->orderBy('id')->lockForUpdate()->get()->keyBy('id') : collect();
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
            $product->fill(collect($data)->except('variants')->all());
            if (! $id) {
                $product->slug = Str::slug($data['name']).'-'.Str::lower(Str::random(8));
            }
            $product->colors = array_values(array_unique(array_column($data['variants'], 'color')));
            $product->save();
            foreach ($data['variants'] as $variant) {
                $values = collect($variant)->except('id')->all();
                if (! empty($variant['id'])) {
                    $existing->get($variant['id'])->update($values);
                } else {
                    $product->variants()->create($values);
                }
            }
            $product->variants()->whereIn('id', $removed)->delete();

            return $product->load('variants');
        }, 3);

        // Disparar Webhook a n8n
        $this->n8nWebhook->dispatch($id ? 'product.updated' : 'product.created', [
            'product_id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'price' => $product->price,
            'model_3d_url' => $product->model_3d_url,
        ], $request->user()?->email);

        return response()->json($product, $id ? 200 : 201);
    }

    public function destroyProduct(int $id)
    {
        // Preserve purchased products and their history; remove from the public catalog.
        DB::transaction(function () use ($id) {
            Product::whereKey($id)->lockForUpdate()->firstOrFail()->update(['active' => false]);
        });

        return response()->json(['message' => 'Producto desactivado.']);
    }

    public function orders()
    {
        return Order::with(['items', 'user:id,name,email'])->latest('id')->paginate(20);
    }

    public function updateOrder(Request $request, int $id)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])]]);

        return DB::transaction(function () use ($data, $id) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            $transitions = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => [], 'cancelled' => []];
            if ($data['status'] === $order->status) {
                return $order->load('items');
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
            $order->update($data);

            return $order->load('items');
        }, 3);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $path = $request->file('image')->store('products', 'public');

        return response()->json([
            'url' => asset('storage/' . $path),
            'path' => $path,
            'message' => 'Imagen subida correctamente.',
        ], 201);
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
                'status' => 'synchronized_with_n8n',
            ],
        };

        $data = array_merge($defaultData, (array) $request->input('data', []));

        $result = $this->n8nWebhook->dispatch(
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
                'title' => 'UrbanSole E-Commerce 3D API - Programación IV (UTN Concordia)',
                'version' => '2.0.0',
                'description' => "API RESTful completa en Laravel 11. \n\n**Cómo probar endpoints protegidos aquí mismo:**\n1. Ejecutá `POST /auth/login` con `admin@urbansole.com` y `password` (hacé clic en **Try it out** → **Execute**).\n2. Copiá el valor de `token` de la respuesta.\n3. Hacé clic en el botón verde **Authorize 🔒** arriba a la derecha, pegá el token y dale a **Authorize**.\n4. ¡Listo! Ya podés crear, editar y eliminar zapatillas en la BD desde los endpoints `/admin/products`.",
            ],
            'servers' => [
                ['url' => 'http://localhost:8000/api', 'description' => 'Servidor Local Laravel'],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum Personal Access Token',
                        'description' => 'Pegá aquí el token devuelto por POST /auth/login (sin escribir Bearer).',
                    ],
                ],
            ],
            'paths' => [
                '/auth/login' => [
                    'post' => [
                        'tags' => ['1. Autenticación Sanctum (Semana 6)'],
                        'summary' => 'Iniciar sesión y obtener Token Sanctum (Usar primero para autorizar)',
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
                        'responses' => ['200' => ['description' => 'Login exitoso y token Sanctum generado']],
                    ],
                ],
                '/auth/register' => [
                    'post' => [
                        'tags' => ['1. Autenticación Sanctum (Semana 6)'],
                        'summary' => 'Registrar nuevo usuario en la BD (y disparar Webhook n8n con Email)',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'email', 'password', 'password_confirmation'],
                                        'properties' => [
                                            'name' => ['type' => 'string', 'example' => 'Juan Pérez'],
                                            'email' => ['type' => 'string', 'example' => 'juan.perez@utn.edu.ar'],
                                            'password' => ['type' => 'string', 'example' => 'password123'],
                                            'password_confirmation' => ['type' => 'string', 'example' => 'password123'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['201' => ['description' => 'Usuario insertado en tabla users y token emitido']],
                    ],
                ],
                '/orders' => [
                    'get' => [
                        'tags' => ['2. Pedidos y Compras'],
                        'summary' => 'Historial de pedidos del cliente autenticado',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Lista paginada de pedidos']],
                    ],
                    'post' => [
                        'tags' => ['2. Pedidos y Compras'],
                        'summary' => 'Crear nueva orden de compra (checkout)',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['201' => ['description' => 'Orden creada']],
                    ],
                ],
                '/products' => [
                    'get' => [
                        'tags' => ['3. Catálogo Público 3D (Semanas 2 a 4)'],
                        'summary' => 'Listar zapatillas de la BD con filtros multicriterio y modelos 3D',
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
                        'tags' => ['4. Administración (Semanas 4, 5 y 7)'],
                        'summary' => 'Listar productos en administración',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Productos admin']],
                    ],
                    'post' => [
                        'tags' => ['4. Administración (Semanas 4, 5 y 7)'],
                        'summary' => 'Insertar nueva zapatilla en administración',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['201' => ['description' => 'Producto creado']],
                    ],
                ],
                '/admin/orders' => [
                    'get' => [
                        'tags' => ['4. Administración (Semanas 4, 5 y 7)'],
                        'summary' => 'Listar todas las órdenes de la tienda para administración',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Lista de órdenes']],
                    ],
                ],
                '/admin/webhooks/n8n' => [
                    'post' => [
                        'tags' => ['5. Automatización n8n y Emails (Semana 15)'],
                        'summary' => 'Disparar evento Webhook HTTP POST a n8n para envío de Email',
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'Webhook procesado']],
                    ],
                ],
            ],
        ]);
    }
}
