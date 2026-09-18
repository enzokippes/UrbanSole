<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_busqueda_insensible_a_mayusculas_y_minusculas(): void
    {
        $nike = Product::create([
            'name' => 'Nike Air Max 90',
            'slug' => 'nike-air-max-90',
            'brand' => 'Nike',
            'description' => 'Zapatilla urbana clasica',
            'price' => 150.00,
            'category' => 'Hombre',
            'active' => true,
        ]);

        $jordan = Product::create([
            'name' => 'Air Jordan 1 Retro',
            'slug' => 'air-jordan-1-retro',
            'brand' => 'Nike',
            'description' => 'Silueta iconica de basketball',
            'price' => 180.00,
            'category' => 'Hombre',
            'active' => true,
        ]);

        $adidas = Product::create([
            'name' => 'Adidas Forum Low',
            'slug' => 'adidas-forum-low',
            'brand' => 'Adidas',
            'description' => 'Estilo retro ochentero',
            'price' => 120.00,
            'category' => 'Hombre',
            'active' => true,
        ]);

        // Busqueda en minusculas "nike"
        $responseLower = $this->getJson('/api/products?search=nike');
        $responseLower->assertStatus(200);
        $this->assertEquals(2, $responseLower->json('total'));

        // Busqueda en mayusculas "NIKE"
        $responseUpper = $this->getJson('/api/products?search=NIKE');
        $responseUpper->assertStatus(200);
        $this->assertEquals(2, $responseUpper->json('total'));

        // Busqueda "jordan" y "JORDAN"
        $responseJordan = $this->getJson('/api/products?search=jordan');
        $responseJordan->assertStatus(200);
        $this->assertEquals(1, $responseJordan->json('total'));
        $this->assertEquals('Air Jordan 1 Retro', $responseJordan->json('data.0.name'));

        $responseJordanUpper = $this->getJson('/api/products?search=JORDAN');
        $responseJordanUpper->assertStatus(200);
        $this->assertEquals(1, $responseJordanUpper->json('total'));
    }

    public function test_navegacion_directa_por_query_params_page_y_search(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Product::create([
                'name' => "Air Modelo {$i}",
                'slug' => "air-modelo-{$i}",
                'brand' => 'Nike',
                'description' => "Descripcion de Air Modelo {$i}",
                'price' => 100 + $i,
                'category' => 'Hombre',
                'active' => true,
            ]);
        }

        $response = $this->getJson('/api/products?page=2&search=Air&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'total',
                'per_page'
            ]);

        $this->assertEquals(2, $response->json('current_page'));
        $this->assertEquals(2, $response->json('last_page'));
        $this->assertEquals(15, $response->json('total'));
        $this->assertCount(5, $response->json('data'));
    }

    public function test_comportamiento_de_limite_cuando_la_pagina_supera_el_total(): void
    {
        Product::create([
            'name' => 'Sneaker Test',
            'slug' => 'sneaker-test',
            'brand' => 'Nike',
            'description' => 'Test',
            'price' => 100,
            'category' => 'Hombre',
            'active' => true,
        ]);

        $response = $this->getJson('/api/products?page=99');

        $response->assertStatus(200);
        $this->assertEquals(99, $response->json('current_page'));
        $this->assertCount(0, $response->json('data'));
    }

    public function test_busqueda_sin_coincidencias_retorna_lista_vacia(): void
    {
        Product::create([
            'name' => 'Nike Air Force',
            'slug' => 'nike-air-force',
            'brand' => 'Nike',
            'description' => 'Clasico blanco',
            'price' => 110,
            'category' => 'Hombre',
            'active' => true,
        ]);

        $response = $this->getJson('/api/products?search=marcaque_no_existe');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('total'));
        $this->assertCount(0, $response->json('data'));
    }

    public function test_productos_incluyen_available_sizes_y_colors_con_stock(): void
    {
        $product = Product::create([
            'name' => 'Jordan 4 Retro',
            'slug' => 'jordan-4-retro',
            'brand' => 'Nike',
            'description' => 'Retro sneaker',
            'price' => 210,
            'category' => 'Hombre',
            'active' => true,
        ]);

        // Variante con stock
        ProductVariant::create([
            'product_id' => $product->id,
            'size' => '42',
            'color' => 'Rojo',
            'stock' => 5,
        ]);

        // Variante sin stock
        ProductVariant::create([
            'product_id' => $product->id,
            'size' => '44',
            'color' => 'Azul',
            'stock' => 0,
        ]);

        $response = $this->getJson('/api/products?search=Jordan');

        $response->assertStatus(200);
        $firstProduct = $response->json('data.0');
        $this->assertContains('42', $firstProduct['available_sizes']);
        $this->assertNotContains('44', $firstProduct['available_sizes']);
        $this->assertContains('Rojo', $firstProduct['available_colors']);
        $this->assertNotContains('Azul', $firstProduct['available_colors']);
    }
}
