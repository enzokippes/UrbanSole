<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class N8nWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.n8n.enabled', true);
        Config::set('services.n8n.webhook_url', 'http://localhost:5678/webhook/urbansole');
        Config::set('services.n8n.notify_email', 'admin@urbansole.com');
    }

    protected function createAdminUser(): User
    {
        return User::create([
            'name' => 'Administrador UTN',
            'email' => 'admin@urbansole.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_user_registration_dispatches_n8n_webhook_with_welcome_email_payload(): void
    {
        Http::fake([
            'http://localhost:5678/webhook/*' => Http::response(['received' => true], 200),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Enzo Kippes',
            'email' => 'enzo@utn.edu.ar',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:5678/webhook/urbansole'
                && $request['event'] === 'user.registered'
                && $request['data']['email'] === 'enzo@utn.edu.ar'
                && $request['email_notification']['to'] === 'enzo@utn.edu.ar'
                && str_contains($request['email_notification']['subject'], 'Bienvenido/a a UrbanSole 3D');
        });
    }

    public function test_product_crud_and_low_stock_dispatch_n8n_webhooks_with_email_notifications(): void
    {
        Http::fake([
            'http://localhost:5678/webhook/*' => Http::response(['received' => true], 200),
        ]);

        $admin = $this->createAdminUser();

        // 1. Crear producto -> dispara product.created
        $createRes = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/products', [
            'name' => 'Air Jordan 1 High Chicago Test',
            'brand' => 'Nike Jordan',
            'category' => 'Hombre',
            'price' => 189.99,
            'active' => true,
            'featured' => true,
            'model_3d_url' => '/models/jordan-chicago.glb',
            'description' => 'Zapatilla de prueba con modelo 3D para verificar webhook n8n.',
            'variants' => [
                ['size' => '42', 'color' => 'Rojo/Blanco', 'stock' => 8],
            ],
        ]);

        $createRes->assertStatus(201);
        $product = $createRes->json();
        $productId = $product['id'];

        Http::assertSent(function ($request) {
            return $request['event'] === 'product.created'
                && $request['data']['name'] === 'Air Jordan 1 High Chicago Test'
                && str_contains($request['email_notification']['subject'], 'Nueva zapatilla publicada');
        });

        // 2. Bajar stock a <= 3 y actualizar producto -> dispara product.updated
        $updateRes = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/products/{$productId}", [
            'name' => 'Air Jordan 1 High Chicago Test',
            'brand' => 'Nike Jordan',
            'category' => 'Hombre',
            'price' => 175.00,
            'active' => true,
            'featured' => true,
            'model_3d_url' => '/models/jordan-chicago.glb',
            'description' => 'Zapatilla de prueba con modelo 3D para verificar webhook n8n.',
            'variants' => [
                ['id' => $product['variants'][0]['id'], 'size' => '42', 'color' => 'Rojo/Blanco', 'stock' => 2],
            ],
        ]);

        $updateRes->assertStatus(200);

        Http::assertSent(fn ($req) => $req['event'] === 'product.updated');

        // 3. Disparar webhook de stock bajo directamente o simular
        $stockLowRes = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/webhooks/n8n', [
            'event' => 'stock.low',
            'notify_email' => 'admin@urbansole.com',
        ]);
        $stockLowRes->assertOk();

        Http::assertSent(fn ($req) => $req['event'] === 'stock.low'
            && str_contains($req['email_notification']['subject'], 'ALERTA DE STOCK'));

        // 4. Eliminar / desactivar producto
        $deleteRes = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/products/{$productId}");
        $deleteRes->assertStatus(200);
    }

    public function test_manual_admin_webhook_endpoint_returns_real_delivery_status(): void
    {
        Http::fake([
            'http://localhost:5678/webhook/*' => Http::response([
                'received' => true,
                'email_sent_to' => 'profe@utn.edu.ar',
            ], 200),
        ]);

        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/webhooks/n8n', [
            'event' => 'catalog.3d.synced',
            'notify_email' => 'profe@utn.edu.ar',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'dispatched')
            ->assertJsonPath('delivered', true)
            ->assertJsonPath('notify_email', 'profe@utn.edu.ar')
            ->assertJsonPath('payload.email_notification.to', 'profe@utn.edu.ar');
    }

    public function test_webhook_service_respects_disabled_flag(): void
    {
        Http::fake();
        Config::set('services.n8n.enabled', false);

        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/webhooks/n8n', [
            'event' => 'catalog.3d.synced',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'disabled')
            ->assertJsonPath('delivered', false);

        Http::assertNothingSent();
    }

    public function test_order_creation_dispatches_receipt_email_and_pdf_attachment_once(): void
    {
        Http::fake([
            'http://localhost:5678/webhook/*' => Http::response(['received' => true], 200),
        ]);

        $customer = User::create([
            'name' => 'Comprador Real',
            'email' => 'comprador@utn.edu.ar',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Air Jordan 1 High Chicago',
            'brand' => 'Nike',
            'description' => 'Modelo de prueba para recibo',
            'slug' => (string) Str::uuid(),
            'price' => 250.00,
            'category' => 'Hombre',
            'active' => true,
        ]);
        $variant = $product->variants()->create(['size' => '42', 'color' => 'Rojo', 'stock' => 10]);

        $payload = [
            'checkout_key' => (string) Str::uuid(),
            'recipient' => 'Comprador Real',
            'phone' => '3454000111',
            'address' => 'Av. Concordia 123',
            'city' => 'Concordia',
            'postal_code' => '3200',
            'items' => [
                ['product_id' => $product->id, 'size' => '42', 'color' => 'Rojo', 'quantity' => 2],
            ],
        ];

        $this->actingAs($customer, 'sanctum')->postJson('/api/orders', $payload)->assertCreated();
        // Reintento idempotente con el mismo checkout_key no vuelve a disparar el webhook
        $this->actingAs($customer, 'sanctum')->postJson('/api/orders', $payload)->assertCreated();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $attachment = $request['email_notification']['attachment'] ?? null;
            $decodedPdf = $attachment ? base64_decode($attachment['content_base64']) : '';

            return $request['event'] === 'order.created'
                && $request['email_notification']['to'] === 'comprador@utn.edu.ar'
                && str_contains($request['email_notification']['subject'], 'Recibo de compra')
                && str_contains($request['email_notification']['html'], 'Air Jordan 1 High Chicago')
                && str_starts_with($decodedPdf, '%PDF');
        });
    }

    public function test_order_status_update_and_checkout_low_stock_dispatch_notifications(): void
    {
        Http::fake([
            'http://localhost:5678/webhook/*' => Http::response(['received' => true], 200),
        ]);

        $admin = $this->createAdminUser();
        $customer = User::create([
            'name' => 'Cliente Fiel',
            'email' => 'cliente.fiel@urbansole.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Jordan 1 Bred',
            'brand' => 'Nike',
            'description' => 'Zapatilla de prueba stock crítico',
            'slug' => (string) Str::uuid(),
            'price' => 210.00,
            'category' => 'Hombre',
            'active' => true,
        ]);
        $product->variants()->create(['size' => '41', 'color' => 'Negro/Rojo', 'stock' => 5]);

        $order = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'checkout_key' => (string) Str::uuid(),
            'recipient' => 'Cliente Fiel',
            'phone' => '3454112233',
            'address' => 'Mitre 300',
            'city' => 'Concordia',
            'postal_code' => '3200',
            'items' => [
                ['product_id' => $product->id, 'size' => '41', 'color' => 'Negro/Rojo', 'quantity' => 3],
            ],
        ])->assertCreated()->json();

        // Al comprar 3 de 5, quedan 2 pares -> dispara order.created (al cliente) y stock.low (al admin)
        Http::assertSent(fn ($req) => $req['event'] === 'order.created'
            && $req['email_notification']['to'] === 'cliente.fiel@urbansole.com');

        Http::assertSent(fn ($req) => $req['event'] === 'stock.low'
            && $req['email_notification']['to'] === 'admin@urbansole.com'
            && $req['data']['min_stock'] === 2
            && str_contains($req['email_notification']['subject'], 'ALERTA DE STOCK'));

        // Admin confirma y envía el pedido -> dispara order.status_updated al correo del cliente
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/orders/{$order['id']}", ['status' => 'confirmed'])->assertOk();
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/orders/{$order['id']}", ['status' => 'shipped'])->assertOk();

        Http::assertSent(fn ($req) => $req['event'] === 'order.status_updated'
            && $req['email_notification']['to'] === 'cliente.fiel@urbansole.com'
            && str_contains($req['email_notification']['subject'], "Tu pedido #{$order['id']} ahora está: Enviado")
            && str_contains($req['email_notification']['html'], 'Jordan 1 Bred'));
    }
}
