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

    public function test_customer_can_cancel_pending_order_and_restores_stock(): void
    {
        $owner = $this->user();
        $this->actingAs($owner);
        $v = $this->variant();
        $id = $this->postJson('/api/orders', $this->payload($v))->assertCreated()->json('id');
        $this->assertEquals(3, $v->fresh()->stock);

        $this->postJson('/api/orders/'.$id.'/cancel')->assertOk()->assertJsonPath('order.status', 'cancelled');
        $this->assertEquals(5, $v->fresh()->stock);

        // No se puede cancelar nuevamente
        $this->postJson('/api/orders/'.$id.'/cancel')->assertUnprocessable();
    }

    public function test_admin_stats_include_top_selling_products_sizes_and_low_stock(): void
    {
        $customer = $this->user();
        $this->actingAs($customer);

        $shoeA = Product::create(['name' => 'Jordan Chicago', 'description' => 'Test', 'brand' => 'Nike', 'slug' => (string) Str::uuid(), 'price' => 200, 'category' => 'Hombre', 'active' => true]);
        $variantA = $shoeA->variants()->create(['size' => '42', 'color' => 'Rojo', 'stock' => 5]);

        $shoeB = Product::create(['name' => 'Runner Pastel', 'description' => 'Test', 'brand' => 'Adidas', 'slug' => (string) Str::uuid(), 'price' => 120, 'category' => 'Mujer', 'active' => true]);
        $variantB = $shoeB->variants()->create(['size' => '38', 'color' => 'Rosa', 'stock' => 2]);

        $this->postJson('/api/orders', $this->payload($variantA, 3))->assertCreated();
        $this->postJson('/api/orders', $this->payload($variantB, 1))->assertCreated();

        $this->actingAs($this->user('admin'));
        $stats = $this->getJson('/api/admin/stats')->assertOk()->json();

        $this->assertEquals(4, $stats['units_sold']);
        $this->assertEquals('Jordan Chicago', $stats['top_selling_products'][0]['product_name']);
        $this->assertEquals(3, $stats['top_selling_products'][0]['units_sold']);
        $this->assertEquals(600, $stats['top_selling_products'][0]['revenue']);
        $this->assertEquals('42', $stats['top_sizes'][0]['size']);
        $this->assertCount(2, $stats['low_stock_items']);
        $this->assertCount(2, $stats['recent_orders']);
    }

    public function test_order_pdf_receipt_download_is_owner_or_admin_only(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $admin = $this->user('admin');
        $v = $this->variant();

        $this->actingAs($owner);
        $orderId = $this->postJson('/api/orders', $this->payload($v, 2))->assertCreated()->json('id');

        $res = $this->get('/api/orders/'.$orderId.'/receipt')->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());

        $this->actingAs($other);
        $this->get('/api/orders/'.$orderId.'/receipt')->assertNotFound();

        $this->actingAs($admin);
        $adminRes = $this->get('/api/admin/orders/'.$orderId.'/receipt')->assertOk();
        $adminRes->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $adminRes->getContent());
    }

    public function test_admin_can_manage_users_roles_bans_and_view_orders(): void
    {
        $customer = $this->user();
        $v = $this->variant();
        $this->actingAs($customer);
        $this->postJson('/api/orders', $this->payload($v, 1))->assertCreated();

        $admin = $this->user('admin');
        $this->actingAs($admin);

        $list = $this->getJson('/api/admin/users')->assertOk()->json('data');
        $this->assertNotEmpty($list);

        $this->getJson('/api/admin/users/'.$customer->id.'/orders')
            ->assertOk()
            ->assertJsonPath('user.id', $customer->id)
            ->assertJsonPath('orders.total', 1);

        $this->patchJson('/api/admin/users/'.$customer->id.'/role', ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');
        $this->assertEquals('admin', $customer->fresh()->role);

        $this->patchJson('/api/admin/users/'.$admin->id.'/role', ['role' => 'customer'])->assertUnprocessable();
        $this->patchJson('/api/admin/users/'.$admin->id.'/ban', ['banned' => true])->assertUnprocessable();

        $this->patchJson('/api/admin/users/'.$customer->id.'/ban', ['banned' => true, 'reason' => 'Spam'])
            ->assertOk()
            ->assertJsonPath('user.ban_reason', 'Spam');
        $this->assertNotNull($customer->fresh()->banned_at);

        $this->patchJson('/api/admin/users/'.$customer->id.'/ban', ['banned' => false])
            ->assertOk()
            ->assertJsonPath('user.banned_at', null);
        $this->assertNull($customer->fresh()->banned_at);
    }

    public function test_admin_can_upload_binary_images_and_glb_models_and_create_custom_shoe(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);

        // Subir un archivo .glb binario desde la PC
        $glbBinary = "glTF\x02\x00\x00\x00\x0C\x00\x00\x00";
        $glbRes = $this->call(
            'POST',
            '/api/admin/upload',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_FILE_NAME' => 'mi-zapatilla-custom.glb',
                'HTTP_X_ASSET_TYPE' => 'model',
            ],
            $glbBinary
        )->assertCreated()->json();

        $this->assertEquals('model', $glbRes['type']);
        $this->assertStringContainsString('/api/media/models/', $glbRes['url']);

        // Verificar que el archivo 3D se sirve con Content-Type model/gltf-binary
        $mediaPath = parse_url($glbRes['url'], PHP_URL_PATH);
        $this->get($mediaPath)->assertOk()->assertHeader('content-type', 'model/gltf-binary');

        // Crear zapatilla nueva sin modelo 3D (solo fotos 2D) y variantes propias
        $customShoe = $this->postJson('/api/admin/products', [
            'name' => 'Zapatilla Custom 2D',
            'brand' => 'Adidas',
            'category' => 'Mujer',
            'price' => 145.50,
            'description' => 'Zapatilla creada desde cero con variantes propias.',
            'model_3d_url' => null,
            'images' => ['/images/precision-low.jpg'],
            'variants' => [
                ['size' => '37', 'color' => 'Blanco/Rosa', 'stock' => 6],
                ['size' => '38', 'color' => 'Blanco/Rosa', 'stock' => 4],
            ],
        ])->assertCreated()->json();

        $this->assertNull($customShoe['model_3d_url']);
        $this->assertCount(2, $customShoe['variants']);
    }

    public function test_admin_can_delete_user_while_preserving_orders_and_sales_stats(): void
    {
        $admin = $this->user('admin');
        $customer = $this->user('customer');
        $customer->createToken('active-session');
        $variant = $this->variant(10);

        $this->actingAs($customer);
        $order = $this->postJson('/api/orders', $this->payload($variant, 2))->assertCreated()->json();

        $this->actingAs($admin);
        $this->patchJson('/api/admin/orders/'.$order['id'], ['status' => 'confirmed'])->assertOk();

        $statsBefore = $this->getJson('/api/admin/stats')->assertOk()->json();
        $this->assertEquals(300.50, (float) $statsBefore['sales_total']);
        $this->assertEquals(1, $statsBefore['total_orders']);
        $this->assertEquals(2, $statsBefore['units_sold']);

        // El admin no puede eliminarse a sí mismo
        $this->deleteJson('/api/admin/users/'.$admin->id)->assertUnprocessable();

        // Eliminar al cliente
        $this->deleteJson('/api/admin/users/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('deleted_user_id', $customer->id)
            ->assertJsonPath('preserved_orders_count', 1);

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $customer->id]);
        $this->assertDatabaseHas('orders', [
            'id' => $order['id'],
            'user_id' => null,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'total' => 300.50,
        ]);

        // Verificar que las estadísticas de ventas y el listado/recibo PDF siguen intactos
        $statsAfter = $this->getJson('/api/admin/stats')->assertOk()->json();
        $this->assertEquals((float) $statsBefore['sales_total'], (float) $statsAfter['sales_total']);
        $this->assertEquals($statsBefore['total_orders'], $statsAfter['total_orders']);
        $this->assertEquals($statsBefore['units_sold'], $statsAfter['units_sold']);
        $this->assertTrue($statsAfter['recent_orders'][0]['user_deleted']);
        $this->assertEquals($customer->email, $statsAfter['recent_orders'][0]['customer_email']);

        $ordersList = $this->getJson('/api/admin/orders')->assertOk()->json('data');
        $this->assertCount(1, $ordersList);
        $this->assertEquals($customer->email, $ordersList[0]['customer_email']);
        $this->assertNull($ordersList[0]['user']);

        $this->get('/api/admin/orders/'.$order['id'].'/receipt')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_each_user_has_independent_sequential_order_numbers(): void
    {
        $userA = $this->user('customer');
        $userB = $this->user('customer');
        $v = $this->variant(20);

        $this->actingAs($userA);
        $a1 = $this->postJson('/api/orders', $this->payload($v, 1))->assertCreated()->json();
        $a2 = $this->postJson('/api/orders', $this->payload($v, 1))->assertCreated()->json();
        $a3 = $this->postJson('/api/orders', $this->payload($v, 1))->assertCreated()->json();

        $this->assertEquals(1, $a1['user_order_number']);
        $this->assertEquals(2, $a2['user_order_number']);
        $this->assertEquals(3, $a3['user_order_number']);

        // Primer pedido de otro usuario empieza en #1 aunque su ID global sea 4
        $this->actingAs($userB);
        $b1 = $this->postJson('/api/orders', $this->payload($v, 1))->assertCreated()->json();

        $this->assertEquals(4, $b1['id']);
        $this->assertEquals(1, $b1['user_order_number']);
    }

    public function test_customer_can_review_product_with_verified_purchase_badge_and_admin_can_quick_restock(): void
    {
        $admin = $this->user('admin');
        $buyer = $this->user('customer');
        $visitor = $this->user('customer');
        $variant = $this->variant(2);

        // Usuario sin compras no puede publicar reseña
        $this->actingAs($visitor)
            ->postJson('/api/products/'.$variant->product_id.'/reviews', [
                'rating' => 3,
                'comment' => 'Nunca compré pero opino igual.',
            ])
            ->assertForbidden();

        // Comprador adquiere el producto (queda pendiente) -> aún no puede reseñar
        $this->actingAs($buyer);
        $order = $this->postJson('/api/orders', $this->payload($variant, 1))->assertCreated()->json();

        $beforeDelivery = $this->getJson('/api/products/'.$variant->product_id)->assertOk()->json();
        $this->assertFalse($beforeDelivery['can_review']);
        $this->assertEquals('pending_delivery', $beforeDelivery['review_eligibility']);

        $this->actingAs($buyer)
            ->postJson('/api/products/'.$variant->product_id.'/reviews', [
                'rating' => 5,
                'comment' => 'Todavía no llegó.',
            ])
            ->assertForbidden();

        // Admin avanza el pedido hasta Entregado (confirmed -> shipped -> delivered)
        $this->actingAs($admin)->patchJson('/api/admin/orders/'.$order['id'], ['status' => 'confirmed'])->assertOk();
        $this->actingAs($admin)->patchJson('/api/admin/orders/'.$order['id'], ['status' => 'shipped'])->assertOk();
        $this->actingAs($admin)->patchJson('/api/admin/orders/'.$order['id'], ['status' => 'delivered'])->assertOk();

        // Ahora el comprador sí está habilitado para reseñar
        $this->actingAs($buyer);
        $afterDelivery = $this->getJson('/api/products/'.$variant->product_id)->assertOk()->json();
        $this->assertTrue($afterDelivery['can_review']);
        $this->assertEquals('can_review', $afterDelivery['review_eligibility']);

        $revBuyer = $this->postJson('/api/products/'.$variant->product_id.'/reviews', [
            'rating' => 5,
            'comment' => 'Excelente calidad y modelo 3D super fiel.',
        ])->assertCreated()->json();

        $this->assertTrue($revBuyer['review']['verified_purchase']);
        $this->assertFalse($revBuyer['can_review']);
        $this->assertEquals('already_reviewed', $revBuyer['review_eligibility']);
        $this->assertEquals(5.0, (float) $revBuyer['reviews_avg_rating']);
        $this->assertEquals(1, $revBuyer['reviews_count']);

        // Mismo comprador no puede volver a opinar (anti-spam: máx 1 reseña por producto)
        $this->actingAs($buyer)
            ->postJson('/api/products/'.$variant->product_id.'/reviews', [
                'rating' => 1,
                'comment' => 'Spam repetido en el mismo producto.',
            ])
            ->assertUnprocessable();

        // Ficha pública devuelve reseñas y promedio (y para el comprador figura already_reviewed)
        $detail = $this->getJson('/api/products/'.$variant->product_id)->assertOk()->json();
        $this->assertFalse($detail['can_review']);
        $this->assertEquals('already_reviewed', $detail['review_eligibility']);
        $this->assertEquals(5.0, (float) $detail['reviews_avg_rating']);
        $this->assertCount(1, $detail['reviews']);

        // Visitante no puede borrar reseña del comprador
        $this->actingAs($visitor)
            ->deleteJson('/api/products/'.$variant->product_id.'/reviews/'.$revBuyer['review']['id'])
            ->assertForbidden();

        // Admin sí puede moderar/eliminar reseñas
        $this->actingAs($admin)
            ->deleteJson('/api/products/'.$variant->product_id.'/reviews/'.$revBuyer['review']['id'])
            ->assertOk()
            ->assertJsonPath('reviews_count', 0);

        // Reposición rápida de stock desde Admin
        $restockRes = $this->actingAs($admin)
            ->patchJson('/api/admin/variants/'.$variant->id.'/stock', ['add_stock' => 10])
            ->assertOk()
            ->json();

        $this->assertEquals(11, $restockRes['new_stock']);
        $this->assertEquals(11, $variant->fresh()->stock);
    }

    public function test_transfer_payment_proof_flow_and_order_rate_limit(): void
    {
        $admin = $this->user('admin');
        $buyer = $this->user('customer');
        $otherCustomer = $this->user('customer');
        $variant = $this->variant(20);

        $this->getJson('/api/checkout/payment-info')
            ->assertOk()
            ->assertJsonStructure(['bank' => ['alias', 'cbu', 'holder', 'bank_name']]);

        $this->actingAs($buyer);
        $orderPayload = array_merge($this->payload($variant, 1), [
            'delivery_method' => 'pickup',
            'payment_method' => 'transfer',
        ]);
        $order = $this->postJson('/api/orders', $orderPayload)
            ->assertCreated()
            ->assertJsonPath('delivery_method', 'pickup')
            ->assertJsonPath('payment_method', 'transfer')
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('has_payment_proof', false)
            ->json();

        // Cliente sube comprobante binario (JPG/PDF) con referencia
        $proofRes = $this->call(
            'POST',
            '/api/orders/'.$order['id'].'/payment-proof',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_FILE_NAME' => 'comprobante-transferencia.pdf',
                'HTTP_X_PAYMENT_REFERENCE' => 'OP-987654',
            ],
            '%PDF-1.4 comprobante demo'
        )->assertOk()->json();

        $this->assertEquals('proof_submitted', $proofRes['order']['payment_status']);
        $this->assertTrue($proofRes['order']['has_payment_proof']);
        $this->assertEquals('OP-987654', $proofRes['order']['payment_reference']);

        // Dueño y Admin pueden descargar el comprobante; otro cliente no
        $this->get('/api/orders/'.$order['id'].'/payment-proof')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($otherCustomer)
            ->get('/api/orders/'.$order['id'].'/payment-proof')
            ->assertNotFound();

        $this->actingAs($admin)
            ->get('/api/admin/orders/'.$order['id'].'/payment-proof')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Admin rechaza el comprobante
        $this->actingAs($admin)
            ->patchJson('/api/admin/orders/'.$order['id'].'/payment', [
                'action' => 'reject',
                'reason' => 'Imagen borrosa',
            ])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'pending')
            ->assertJsonPath('order.has_payment_proof', false);

        // Cliente sube de nuevo y Admin aprueba el pago -> acredita y confirma pedido
        $this->actingAs($buyer);
        $this->call(
            'POST',
            '/api/orders/'.$order['id'].'/payment-proof',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_FILE_NAME' => 'comprobante-valido.jpg',
            ],
            'fake-jpg-binary-data'
        )->assertOk();

        $this->actingAs($admin)
            ->patchJson('/api/admin/orders/'.$order['id'].'/payment', [
                'action' => 'approve',
            ])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.status', 'confirmed');

        // Rate limit: máximo 5 pedidos cada 2 minutos por usuario (ya hizo 1 arriba -> 4 más OK, el 6to devuelve 429)
        $this->actingAs($buyer);
        for ($i = 2; $i <= 5; $i++) {
            $this->postJson('/api/orders', $this->payload($variant, 1))->assertCreated();
        }
        $this->postJson('/api/orders', $this->payload($variant, 1))->assertStatus(429);
    }

    public function test_card_payment_validates_luhn_stores_only_masked_reference_and_auto_confirms(): void
    {
        $buyer = $this->user('customer');
        $variant = $this->variant(10);
        $this->actingAs($buyer);

        // Tarjeta con número inválido (no pasa Luhn)
        $this->postJson('/api/orders', array_merge($this->payload($variant, 1), [
            'payment_method' => 'card',
            'card_holder' => 'Juan Perez',
            'card_number' => '4532 0151 1283 0360',
            'card_expiry' => '12/29',
            'card_cvv' => '123',
            'card_installments' => 3,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['card_number']);

        // Tarjeta válida -> aprueba pago automáticamente y solo guarda marca + últimos 4 dígitos
        $order = $this->postJson('/api/orders', array_merge($this->payload($variant, 1), [
            'payment_method' => 'card',
            'card_holder' => 'Juan Perez',
            'card_number' => '4532 0151 1283 0366',
            'card_expiry' => '12/29',
            'card_cvv' => '123',
            'card_installments' => 3,
        ]))
            ->assertCreated()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('payment_method', 'card')
            ->json();

        $this->assertStringStartsWith('Visa •••• 0366 · 3 cuotas · Aut. ', $order['payment_reference']);
        $this->assertStringNotContainsString('4532', $order['payment_reference']);
        $this->assertDatabaseMissing('orders', ['payment_reference' => '4532015112830366']);
    }
}
