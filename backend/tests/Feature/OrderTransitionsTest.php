<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): User
    {
        return User::create([
            'name' => 'Cliente Test',
            'email' => Str::uuid() . '@urbansole.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => Str::uuid() . '@urbansole.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    private function createOrder(User $user, string $status = 'pending'): Order
    {
        $product = Product::create([
            'name' => 'Zapatilla Test',
            'description' => 'Test Desc',
            'brand' => 'Nike',
            'slug' => (string) Str::uuid(),
            'price' => 120.00,
            'category' => 'Hombre',
            'active' => true,
        ]);

        $variant = $product->variants()->create([
            'size' => '42',
            'color' => 'Negro',
            'stock' => 10,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'checkout_key' => (string) Str::uuid(),
            'status' => $status,
            'recipient' => 'Juan Perez',
            'phone' => '3454123456',
            'address' => 'Entre Rios 100',
            'city' => 'Concordia',
            'postal_code' => '3200',
            'total' => 120.00,
        ]);

        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'size' => $variant->size,
            'color' => $variant->color,
            'quantity' => 1,
            'unit_price' => $product->price,
        ]);

        return $order;
    }

    public function test_admin_can_advance_order_lifecycle(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();
        $order = $this->createOrder($customer, 'pending');

        $this->actingAs($admin);

        // pending -> confirmed
        $response = $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'confirmed']);
        $response->assertOk();
        $this->assertEquals('confirmed', $order->fresh()->status);

        // confirmed -> shipped
        $response = $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'shipped']);
        $response->assertOk();
        $this->assertEquals('shipped', $order->fresh()->status);

        // shipped -> delivered
        $response = $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'delivered']);
        $response->assertOk();
        $this->assertEquals('delivered', $order->fresh()->status);
    }

    public function test_illegal_order_transition_is_rejected(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();
        $order = $this->createOrder($customer, 'pending');

        $this->actingAs($admin);

        // Intento de saltar directo de pending a delivered
        $response = $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'delivered']);
        $response->assertUnprocessable();

        // Si ya está entregado, no puede cancelarse
        $order->update(['status' => 'delivered']);
        $response = $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'cancelled']);
        $response->assertUnprocessable();
    }

    public function test_customer_cannot_modify_orders_via_admin_endpoint(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer, 'pending');

        $this->actingAs($customer);

        $response = $this->patchJson("/api/admin/orders/{$order->id}", ['status' => 'confirmed']);
        $response->assertForbidden();
    }

    public function test_advance_endpoint_progresses_order_step(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer, 'pending');

        $this->actingAs($customer);

        $response = $this->postJson("/api/orders/{$order->id}/advance");
        $response->assertOk();
        $this->assertEquals('confirmed', $order->fresh()->status);
    }
}
