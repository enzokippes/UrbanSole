<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\N8nWebhookService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        protected N8nWebhookService $n8nWebhook
    ) {}

    public function stats()
    {
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
            'by_category' => $byCategory,
            'n8n_config' => [
                'webhook_url' => config('services.n8n.webhook_url'),
                'enabled' => (bool) config('services.n8n.enabled'),
                'notify_email' => config('services.n8n.notify_email'),
            ],
            'message' => 'Admin stats access granted',
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
                '/auth/oauth' => [
                    'post' => [
                        'tags' => ['1. Autenticación Sanctum (Semana 6)'],
                        'summary' => 'Login con Proveedor de Identidad OAuth 2.0 (Google / GitHub - Semana 7)',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['provider'],
                                        'properties' => [
                                            'provider' => ['type' => 'string', 'enum' => ['google', 'github'], 'example' => 'google'],
                                            'email' => ['type' => 'string', 'example' => 'alumno.utn@gmail.com'],
                                            'name' => ['type' => 'string', 'example' => 'Alumno UTN Google'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'Autenticación OAuth 2.0 exitosa']],
                    ],
                ],
                '/products' => [
                    'get' => [
                        'tags' => ['2. Catálogo Público 3D (Semanas 2 a 4)'],
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
                '/products/{id}' => [
                    'get' => [
                        'tags' => ['2. Catálogo Público 3D (Semanas 2 a 4)'],
                        'summary' => 'Consultar detalle de una zapatilla por ID o slug',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'example' => '1']],
                        ],
                        'responses' => ['200' => ['description' => 'Detalle del producto con sus variantes y modelos 3D']],
                    ],
                ],
                '/admin/products' => [
                    'post' => [
                        'tags' => ['3. Administración CRUD en BD (Semanas 4, 5 y 7)'],
                        'summary' => 'INSERTAR nueva zapatilla 3D en la Base de Datos (StoreProductRequest)',
                        'security' => [['bearerAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'brand', 'category', 'price', 'description'],
                                        'properties' => [
                                            'name' => ['type' => 'string', 'example' => 'Nike Air Force 1 Low Mocha Especial'],
                                            'brand' => ['type' => 'string', 'example' => 'Nike'],
                                            'category' => ['type' => 'string', 'enum' => ['Hombre', 'Mujer', 'Niño'], 'example' => 'Hombre'],
                                            'price' => ['type' => 'number', 'example' => 165.00],
                                            'original_price' => ['type' => 'number', 'example' => 190.00],
                                            'model_3d_url' => ['type' => 'string', 'example' => '/models/af1-mocha.glb'],
                                            'description' => ['type' => 'string', 'example' => 'Zapatilla urbana creada desde el endpoint POST de la API RESTful.'],
                                            'featured' => ['type' => 'boolean', 'example' => true],
                                            'active' => ['type' => 'boolean', 'example' => true],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Producto creado en la BD con sus variantes de talle y stock'],
                            '422' => ['description' => 'Error de validación FormRequest (Semana 5)'],
                        ],
                    ],
                ],
                '/admin/products/{id}' => [
                    'put' => [
                        'tags' => ['3. Administración CRUD en BD (Semanas 4, 5 y 7)'],
                        'summary' => 'ACTUALIZAR zapatilla existente en la Base de Datos (UpdateProductRequest)',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'example' => 1]],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string', 'example' => 'Air Jordan 1 High OG "Chicago" (Edición Limitada)'],
                                            'price' => ['type' => 'number', 'example' => 199.99],
                                            'category' => ['type' => 'string', 'enum' => ['Hombre', 'Mujer', 'Niño'], 'example' => 'Hombre'],
                                            'model_3d_url' => ['type' => 'string', 'example' => '/models/jordan-chicago.glb'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'Producto actualizado en la BD']],
                    ],
                    'delete' => [
                        'tags' => ['3. Administración CRUD en BD (Semanas 4, 5 y 7)'],
                        'summary' => 'ELIMINAR zapatilla de la Base de Datos',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'example' => 12]],
                        ],
                        'responses' => ['200' => ['description' => 'Producto eliminado de la BD']],
                    ],
                ],
                '/admin/webhooks/n8n' => [
                    'post' => [
                        'tags' => ['4. Automatización n8n y Emails (Semana 15)'],
                        'summary' => 'Disparar evento Webhook HTTP POST a n8n para envío de Email',
                        'security' => [['bearerAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'event' => [
                                                'type' => 'string',
                                                'enum' => ['user.registered', 'product.created', 'stock.low', 'catalog.3d.synced'],
                                                'example' => 'user.registered',
                                            ],
                                            'notify_email' => ['type' => 'string', 'example' => 'profesor@utn.edu.ar'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'Webhook procesado y plantilla de email generada']],
                    ],
                ],
            ],
        ]);
    }
}
