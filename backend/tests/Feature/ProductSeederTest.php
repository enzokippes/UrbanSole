<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_seeder_populates_all_catalog_products(): void
    {
        $count = Product::count();
        $this->assertGreaterThanOrEqual(12, $count, 'El seeder debe cargar al menos 12 productos del catálogo.');
        $this->assertDatabaseHas('products', ['active' => true]);
    }

    public function test_seeder_products_have_variants_with_valid_stock(): void
    {
        $products = Product::with('variants')->get();
        $this->assertNotEmpty($products);

        foreach ($products as $product) {
            $this->assertNotEmpty($product->variants, "El producto '{$product->name}' debe tener al menos una variante.");

            foreach ($product->variants as $variant) {
                $this->assertNotEmpty($variant->size, 'La variante debe tener un talle asignado.');
                $this->assertNotEmpty($variant->color, 'La variante debe tener un color asignado.');
                $this->assertGreaterThanOrEqual(0, $variant->stock, 'El stock no puede ser negativo.');
            }
        }
    }

    public function test_seeder_products_have_valid_3d_models_and_positive_prices(): void
    {
        $products = Product::all();

        foreach ($products as $product) {
            $this->assertGreaterThan(0, (float) $product->price, "El precio de '{$product->name}' debe ser positivo.");
            $this->assertNotNull($product->model_3d_url, "El producto '{$product->name}' debe contar con un modelo 3D.");
            $this->assertStringEndsWith('.glb', $product->model_3d_url, 'El archivo 3D debe tener extensión .glb');
        }
    }

    public function test_seeder_covers_all_required_categories(): void
    {
        $categories = Product::select('category')->distinct()->pluck('category')->toArray();

        $this->assertContains('Hombre', $categories);
        $this->assertContains('Mujer', $categories);
        $this->assertContains('Niño', $categories);
    }
}
