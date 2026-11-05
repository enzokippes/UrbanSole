<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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
            'model_3d_url' => '/models/jordan-chicago.glb',
            'description' => 'Zapatilla de prueba con modelo 3D para verificar webhook n8n.',
        ]);

        $createRes->assertStatus(201);
        $productId = $createRes->json('data.id');

        Http::assertSent(function ($request) {
            return $request['event'] === 'product.created'
                && $request['data']['name'] === 'Air Jordan 1 High Chicago Test'
                && str_contains($request['email_notification']['subject'], 'Nueva zapatilla publicada');
        });

        // 2. Bajar stock a <= 3 y actualizar producto -> dispara product.updated + stock.low
        ProductVariant::where('product_id', $productId)->first()?->update(['stock' => 2]);

        $updateRes = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/products/{$productId}", [
            'price' => 175.00,
        ]);

        $updateRes->assertStatus(200);

        Http::assertSent(fn ($req) => $req['event'] === 'product.updated');
        Http::assertSent(fn ($req) => $req['event'] === 'stock.low'
            && str_contains($req['email_notification']['subject'], 'ALERTA DE STOCK'));

        // 3. Eliminar producto -> dispara product.deleted
        $deleteRes = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/products/{$productId}");
        $deleteRes->assertStatus(200);

        Http::assertSent(fn ($req) => $req['event'] === 'product.deleted');
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
}
