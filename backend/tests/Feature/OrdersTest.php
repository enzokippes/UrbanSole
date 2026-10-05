<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'customer'): User
    {
        return User::create(['name' => 'Test', 'email' => Str::uuid().'@test.com', 'password' => bcrypt('password123'), 'role' => $role]);
    }

    private function variant(int $stock = 5): ProductVariant
    {
        $p = Product::create(['name' => 'Shoe', 'description' => 'Test', 'brand' => 'Nike', 'slug' => (string) Str::uuid(), 'price' => 150.25, 'category' => 'Hombre', 'active' => true]);

        return $p->variants()->create(['size' => '40', 'color' => 'Negro', 'stock' => $stock]);
    }

    private function payload(ProductVariant $v, int $quantity = 2): array
    {
        return ['checkout_key' => (string) Str::uuid(), 'recipient' => 'Cliente', 'phone' => '123', 'address' => 'Calle 1', 'city' => 'Concordia', 'postal_code' => '3200', 'items' => [['product_id' => $v->product_id, 'size' => $v->size, 'color' => $v->color, 'quantity' => $quantity, 'price' => 0.01]]];
    }

    public function test_server_prices_stock_and_retry(): void
    {
        $this->actingAs($this->user());
        $v = $this->variant();
        $data = $this->payload($v);
        $id = $this->postJson('/api/orders', $data)->assertCreated()->assertJsonPath('total', '300.50')->json('id');
        $this->assertEquals(3, $v->fresh()->stock);
        $this->postJson('/api/orders', $data)->assertCreated()->assertJsonPath('id', $id);
        $this->assertEquals(3, $v->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_failure_rolls_back_all_stock(): void
    {
        $this->actingAs($this->user());
        $a = $this->variant();
        $b = $this->variant(0);
        $data = $this->payload($a);
        $data['items'][] = $this->payload($b)['items'][0];
        $this->postJson('/api/orders', $data)->assertUnprocessable();
        $this->assertEquals(5, $a->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_duplicate_lines_cannot_bypass_stock(): void
    {
        $this->actingAs($this->user());
        $v = $this->variant(3);
        $data = $this->payload($v);
        $data['items'][] = $data['items'][0];
        $this->postJson('/api/orders', $data)->assertUnprocessable();
        $this->assertEquals(3, $v->fresh()->stock);
    }

    public function test_history_is_owner_only(): void
    {
        $owner = $this->user();
        $this->actingAs($owner);
        $v = $this->variant();
        $id = $this->postJson('/api/orders', $this->payload($v))->assertCreated()->json('id');
        $this->getJson('/api/orders')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/orders/'.$id)->assertOk();
        $this->actingAs($this->user());
        $this->getJson('/api/orders/'.$id)->assertNotFound();
        $this->getJson('/api/orders')->assertJsonPath('total', 0);
    }

    public function test_cancel_restores_once_and_terminal_status_cannot_reopen(): void
    {
        $this->actingAs($this->user());
        $v = $this->variant();
        $id = $this->postJson('/api/orders', $this->payload($v))->assertCreated()->json('id');
        $this->actingAs($this->user('admin'));
        $this->patchJson('/api/admin/orders/'.$id, ['status' => 'cancelled'])->assertOk();
        $this->patchJson('/api/admin/orders/'.$id, ['status' => 'cancelled'])->assertOk();
        $this->assertEquals(5, $v->fresh()->stock);
        $this->patchJson('/api/admin/orders/'.$id, ['status' => 'pending'])->assertUnprocessable();
    }

    public function test_admin_permissions_crud_and_stats(): void
    {
        $this->actingAs($this->user());
        $this->getJson('/api/admin/products')->assertForbidden();
        $this->postJson('/api/admin/products', [])->assertForbidden();
        $this->getJson('/api/admin/orders')->assertForbidden();
        $this->actingAs($this->user('admin'));
        $data = ['name' => 'New Shoe', 'brand' => 'Nike', 'description' => 'Test', 'category' => 'Hombre', 'price' => 100, 'active' => true, 'featured' => false, 'variants' => [['size' => '40', 'color' => 'Negro', 'stock' => 3]]];
        $product = $this->postJson('/api/admin/products', $data)->assertCreated()->json();
        $data['variants'][0]['id'] = $product['variants'][0]['id'];
        $data['variants'][0]['stock'] = 4;
        $this->putJson('/api/admin/products/'.$product['id'], $data)->assertOk()->assertJsonPath('variants.0.stock', 4);
        $this->deleteJson('/api/admin/products/'.$product['id'])->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product['id'], 'active' => false]);
        $this->getJson('/api/admin/stats')->assertOk()->assertJsonPath('total_products', 1)->assertJsonPath('total_orders', 0);
    }

    public function test_sales_count_only_confirmed_orders(): void
    {
        $this->actingAs($this->user());
        $v = $this->variant();
        $id = $this->postJson('/api/orders', $this->payload($v))->assertCreated()->json('id');
        $this->actingAs($this->user('admin'));
        $this->getJson('/api/admin/stats')->assertOk()->assertJsonPath('sales_total', 0);
        $this->patchJson('/api/admin/orders/'.$id, ['status' => 'delivered'])->assertUnprocessable();
        $this->patchJson('/api/admin/orders/'.$id, ['status' => 'confirmed'])->assertOk();
        $this->assertEquals(300.50, (float) $this->getJson('/api/admin/stats')->json('sales_total'));
    }

    public function test_inactive_product_and_invalid_quantity_are_rejected(): void
    {
        $this->actingAs($this->user());
        $v = $this->variant();
        $this->postJson('/api/orders', $this->payload($v, -1))->assertUnprocessable();
        $v->product->update(['active' => false]);
        $this->postJson('/api/orders', $this->payload($v))->assertUnprocessable();
        $this->assertEquals(5, $v->fresh()->stock);
    }

    public function test_admin_cannot_remove_purchased_variant_and_history_survives_product_deactivation(): void
    {
        $owner = $this->user();
        $this->actingAs($owner);
        $v = $this->variant();
        $id = $this->postJson('/api/orders', $this->payload($v))->assertCreated()->json('id');
        $this->actingAs($this->user('admin'));
        $data = $v->product->toArray();
        $data['variants'] = [['size' => '42', 'color' => 'Negro', 'stock' => 2]];
        $this->putJson('/api/admin/products/'.$v->product_id, $data)->assertUnprocessable();
        $this->deleteJson('/api/admin/products/'.$v->product_id)->assertOk();
        $this->actingAs($owner);
        $this->getJson('/api/orders/'.$id)->assertOk()->assertJsonPath('items.0.product_name', 'Shoe');
    }
}
