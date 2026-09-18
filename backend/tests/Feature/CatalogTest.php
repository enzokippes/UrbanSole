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

    /**
     * Matriz de pruebas de integración para Historia 2.2:
     * Filtrado combinatorio multicriterio (Categoría + 2 Talles + 1 Color + Rango de precio) y ordenamiento con whitelist.
     */
    public function test_filtrado_combinatorio_categoria_dos_talles_un_color_y_rango_de_precios(): void
    {
        // 1. Producto que cumple todas las condiciones
        $p1 = Product::create([
            'name' => 'Sneaker Match 1',
            'slug' => 'sneaker-match-1',
            'brand' => 'Nike',
            'description' => 'Calzado deportivo',
            'price' => 150.00,
            'category' => 'Hombre',
            'colors' => ['Rojo', 'Negro'],
            'active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $p1->id,
            'size' => '41',
            'color' => 'Rojo',
            'stock' => 4,
        ]);

        // 2. Producto que cumple pero con el segundo talle permitido (42)
        $p2 = Product::create([
            'name' => 'Sneaker Match 2',
            'slug' => 'sneaker-match-2',
            'brand' => 'Nike',
            'description' => 'Calzado urbano',
            'price' => 180.00,
            'category' => 'Hombre',
            'colors' => ['Rojo'],
            'active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $p2->id,
            'size' => '42',
            'color' => 'Rojo',
            'stock' => 2,
        ]);

        // 3. Falla por categoria (Mujer)
        $p3 = Product::create([
            'name' => 'Sneaker Fail Categoria',
            'slug' => 'sneaker-fail-categoria',
            'brand' => 'Nike',
            'description' => 'Calzado mujer',
            'price' => 150.00,
            'category' => 'Mujer',
            'colors' => ['Rojo'],
            'active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $p3->id,
            'size' => '41',
            'color' => 'Rojo',
            'stock' => 3,
        ]);

        // 4. Falla por color (Azul)
        $p4 = Product::create([
            'name' => 'Sneaker Fail Color',
            'slug' => 'sneaker-fail-color',
            'brand' => 'Nike',
            'description' => 'Calzado azul',
            'price' => 150.00,
            'category' => 'Hombre',
            'colors' => ['Azul'],
            'active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $p4->id,
            'size' => '41',
            'color' => 'Azul',
            'stock' => 3,
        ]);

        // 5. Falla por precio fuera de rango (250)
        $p5 = Product::create([
            'name' => 'Sneaker Fail Precio',
            'slug' => 'sneaker-fail-precio',
            'brand' => 'Nike',
            'description' => 'Calzado caro',
            'price' => 250.00,
            'category' => 'Hombre',
            'colors' => ['Rojo'],
            'active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $p5->id,
            'size' => '41',
            'color' => 'Rojo',
            'stock' => 3,
        ]);

        // 6. Falla por talle con stock = 0
        $p6 = Product::create([
            'name' => 'Sneaker Fail Sin Stock',
            'slug' => 'sneaker-fail-sin-stock',
            'brand' => 'Nike',
            'description' => 'Calzado sin stock en 41',
            'price' => 160.00,
            'category' => 'Hombre',
            'colors' => ['Rojo'],
            'active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $p6->id,
            'size' => '41',
            'color' => 'Rojo',
            'stock' => 0,
        ]);

        // Ejecutar matriz de pruebas combinatoria
        $response = $this->getJson('/api/products?category=Hombre&size=41,42&color=Rojo&min_price=100&max_price=200');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total'));
        $this->assertCount(2, $response->json('data'));

        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Sneaker Match 1', $names);
        $this->assertContains('Sneaker Match 2', $names);
        $this->assertNotContains('Sneaker Fail Categoria', $names);
        $this->assertNotContains('Sneaker Fail Color', $names);
        $this->assertNotContains('Sneaker Fail Precio', $names);
        $this->assertNotContains('Sneaker Fail Sin Stock', $names);
    }

    public function test_contador_de_resultados_coincide_exactamente_con_productos_renderizados(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Product::create([
                'name' => "Producto Test {$i}",
                'slug' => "producto-test-{$i}",
                'brand' => 'TestBrand',
                'description' => "Descripcion {$i}",
                'price' => 100,
                'category' => 'Hombre',
                'active' => true,
            ]);
        }

        $response = $this->getJson('/api/products?per_page=12');

        $response->assertStatus(200);
        $total = $response->json('total');
        $renderedCount = count($response->json('data'));

        $this->assertEquals(5, $total);
        $this->assertEquals($total, $renderedCount);
    }

    public function test_ordenamiento_con_whitelist_estricta_previene_inyeccion_sql(): void
    {
        Product::create([
            'name' => 'B Zapatilla',
            'slug' => 'b-zapatilla',
            'brand' => 'Nike',
            'description' => 'Test',
            'price' => 200.00,
            'category' => 'Hombre',
            'active' => true,
        ]);

        Product::create([
            'name' => 'A Zapatilla',
            'slug' => 'a-zapatilla',
            'brand' => 'Nike',
            'description' => 'Test',
            'price' => 100.00,
            'category' => 'Hombre',
            'active' => true,
        ]);

        // Orden ascendente por precio
        $resAsc = $this->getJson('/api/products?sort_by=price&sort_dir=asc');
        $resAsc->assertStatus(200);
        $this->assertEquals(100.00, (float) $resAsc->json('data.0.price'));

        // Orden descendente por precio
        $resDesc = $this->getJson('/api/products?sort_by=price&sort_dir=desc');
        $resDesc->assertStatus(200);
        $this->assertEquals(200.00, (float) $resDesc->json('data.0.price'));

        // Parametro malicioso no permitido no rompe la consulta y cae en fallback seguro
        $resMalicious = $this->getJson('/api/products?sort_by=sleep(5)--&sort_dir=desc');
        $resMalicious->assertStatus(200);
    }
}

