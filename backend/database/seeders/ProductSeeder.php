<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    private array $products = [
        [
            'name' => 'Air Jordan 1 Retro High OG "Chicago"',
            'brand' => 'Nike',
            'description' => 'El ícono legendario de 1985 con la mítica combinación Chicago (Rojo, Blanco y Negro). Silueta de caña alta en cuero premium con amortiguación Air encapsulada.',
            'price' => 180.00,
            'original_price' => 210.00,
            'category' => 'Hombre',
            'featured' => true,
            'colors' => ['Chicago (Rojo/Blanco/Negro)', 'Bred (Rojo/Negro)', 'Royal Blue (Azul/Negro)'],
            'color_images' => [
                'Chicago (Rojo/Blanco/Negro)' => '/images/jordan-chicago.jpg',
                'Bred (Rojo/Negro)'           => '/images/jordan-bred.jpg',
                'Royal Blue (Azul/Negro)'     => '/images/jordan-royal.jpg',
            ],
            'tags' => ['jordan', 'basketball', 'retro', 'high-top'],
            'model_3d_url' => '/models/deportiva.glb',
            'images' => [
                '/images/jordan-chicago.jpg',
                '/images/jordan-bred.jpg',
                '/images/jordan-royal.jpg',
            ],
        ],
        [
            'name' => 'Air Jordan 1 High "Mocha Earth"',
            'brand' => 'Nike',
            'description' => 'Elegancia y estilo urbano con paneles en gamuza marrón y cuero blanco marfil. Una de las combinaciones de colores más codiciadas de la silueta Jordan 1.',
            'price' => 195.00,
            'original_price' => null,
            'category' => 'Hombre',
            'featured' => true,
            'colors' => ['Mocha Earth (Marrón/Blanco)', 'Smoke Grey (Gris/Blanco)', 'Chicago (Rojo/Blanco)'],
            'color_images' => [
                'Mocha Earth (Marrón/Blanco)' => '/images/jordan-mocha.jpg',
                'Smoke Grey (Gris/Blanco)'     => '/images/jordan-smoke-grey.jpg',
                'Chicago (Rojo/Blanco)'        => '/images/jordan-chicago.jpg',
            ],
            'tags' => ['jordan', 'earth-tones', 'lifestyle', 'premium'],
            'model_3d_url' => '/models/urbana.glb',
            'images' => [
                '/images/jordan-mocha.jpg',
                '/images/jordan-smoke-grey.jpg',
                '/images/jordan-chicago.jpg',
            ],
        ],
        [
            'name' => 'Air Jordan 1 Retro High "Royal Edition"',
            'brand' => 'Nike',
            'description' => 'Un clásico infaltable en azul profundo y negro. Paneles de cuero liso con detalles micro-perforados en la puntera y suela de tracción circular.',
            'price' => 185.00,
            'original_price' => null,
            'category' => 'Hombre',
            'featured' => true,
            'colors' => ['Royal Blue (Azul/Negro)', 'Bred (Rojo/Negro)', 'Smoke Grey (Gris/Blanco)'],
            'color_images' => [
                'Royal Blue (Azul/Negro)'  => '/images/jordan-royal.jpg',
                'Bred (Rojo/Negro)'        => '/images/jordan-bred.jpg',
                'Smoke Grey (Gris/Blanco)' => '/images/jordan-smoke-grey.jpg',
            ],
            'tags' => ['jordan', 'royal', 'basketball', 'high-top'],
            'model_3d_url' => '/models/basq.glb',
            'images' => [
                '/images/jordan-royal.jpg',
                '/images/jordan-bred.jpg',
                '/images/jordan-smoke-grey.jpg',
            ],
        ],
        [
            'name' => 'Air Jordan 1 High "Sunset Special"',
            'brand' => 'Nike',
            'description' => 'Edición especial de contrastes cálidos y degradados estilo atardecer. Estructura de cuero acolchado con el icónico logo Wings en el lateral.',
            'price' => 190.00,
            'original_price' => 220.00,
            'category' => 'Hombre',
            'featured' => false,
            'colors' => ['Sunset Multicolor', 'Mocha Earth (Marrón/Blanco)', 'Chicago (Rojo/Blanco)'],
            'color_images' => [
                'Sunset Multicolor'            => '/images/jordan-sunset.jpg',
                'Mocha Earth (Marrón/Blanco)' => '/images/jordan-mocha.jpg',
                'Chicago (Rojo/Blanco)'        => '/images/jordan-chicago.jpg',
            ],
            'tags' => ['jordan', 'sunset', 'special-edition', 'retro'],
            'model_3d_url' => '/models/jordan.glb',
            'images' => [
                '/images/jordan-sunset.jpg',
                '/images/jordan-mocha.jpg',
                '/images/jordan-chicago.jpg',
            ],
        ],
        [
            'name' => 'Nike Air Velocity Sport Runner',
            'brand' => 'Nike',
            'description' => 'Zapatilla deportiva de alto rendimiento y estilo moderno. Malla técnica ultra transpirable con amortiguación reactiva en la suela para máxima amortiguación.',
            'price' => 145.00,
            'original_price' => 165.00,
            'category' => 'Mujer',
            'featured' => true,
            'colors' => ['Sport Coral (Coral/Rosa)', 'Midnight Blue (Azul)', 'Triple Black (Negro)'],
            'color_images' => [
                'Sport Coral (Coral/Rosa)'  => '/images/velocity-coral.jpg',
                'Midnight Blue (Azul)'      => '/images/velocity-blue.jpg',
                'Triple Black (Negro)'      => '/images/velocity-black.jpg',
            ],
            'tags' => ['running', 'air-max', 'performance', 'women'],
            'model_3d_url' => '/models/sneaker.glb',
            'images' => [
                '/images/velocity-coral.jpg',
                '/images/velocity-blue.jpg',
                '/images/velocity-black.jpg',
            ],
        ],
        [
            'name' => 'Nike Air Velocity Runner Pro',
            'brand' => 'Nike',
            'description' => 'Diseñada para corredores exigentes. Suela de espuma de doble densidad con sujeción ergonómica en el mediopié y tracción en todo tipo de superficies.',
            'price' => 155.00,
            'original_price' => null,
            'category' => 'Hombre',
            'featured' => false,
            'colors' => ['Triple Black (Negro)', 'Midnight Blue (Azul)', 'Sport Coral (Coral/Rosa)'],
            'color_images' => [
                'Triple Black (Negro)'      => '/images/velocity-black.jpg',
                'Midnight Blue (Azul)'      => '/images/velocity-blue.jpg',
                'Sport Coral (Coral/Rosa)'  => '/images/velocity-coral.jpg',
            ],
            'tags' => ['running', 'pro', 'performance', 'mens'],
            'model_3d_url' => '/models/sneaker.glb',
            'images' => [
                '/images/velocity-black.jpg',
                '/images/velocity-blue.jpg',
                '/images/velocity-coral.jpg',
            ],
        ],
        [
            'name' => 'Air Jordan 1 High "Smoke Grey Edition"',
            'brand' => 'Nike',
            'description' => 'Silueta limpia y versátil en tonos monocromáticos grises y blancos con acentos negros. Combina con cualquier look urbano femenino.',
            'price' => 175.00,
            'original_price' => 190.00,
            'category' => 'Mujer',
            'featured' => true,
            'colors' => ['Smoke Grey (Gris/Blanco)', 'Sunset Multicolor', 'Chicago (Rojo/Blanco)'],
            'color_images' => [
                'Smoke Grey (Gris/Blanco)' => '/images/jordan-smoke-grey.jpg',
                'Sunset Multicolor'        => '/images/jordan-sunset.jpg',
                'Chicago (Rojo/Blanco)'    => '/images/jordan-chicago.jpg',
            ],
            'tags' => ['jordan', 'women', 'smoke-grey', 'lifestyle'],
            'model_3d_url' => '/models/dama.glb',
            'images' => [
                '/images/jordan-smoke-grey.jpg',
                '/images/jordan-sunset.jpg',
                '/images/jordan-chicago.jpg',
            ],
        ],
        [
            'name' => 'Air Jordan 1 High OG Kids "Heritage"',
            'brand' => 'Nike',
            'description' => 'Toda la herencia y resistencia del modelo original adaptado a los pies de los más chicos. Materiales duraderos, sujeción firme y estilo legendario.',
            'price' => 85.00,
            'original_price' => 95.00,
            'category' => 'Niño',
            'featured' => true,
            'colors' => ['Chicago (Rojo/Blanco)', 'Royal Blue (Azul/Negro)', 'Sunset Multicolor'],
            'color_images' => [
                'Chicago (Rojo/Blanco)'    => '/images/jordan-chicago.jpg',
                'Royal Blue (Azul/Negro)'  => '/images/jordan-royal.jpg',
                'Sunset Multicolor'        => '/images/jordan-sunset.jpg',
            ],
            'tags' => ['kids', 'jordan', 'heritage', 'high-top'],
            'model_3d_url' => '/models/kids.glb',
            'images' => [
                '/images/jordan-chicago.jpg',
                '/images/jordan-royal.jpg',
                '/images/jordan-sunset.jpg',
            ],
        ],
    ];

    public function run(): void
    {
        $menSizes = ['39', '40', '41', '42', '43', '44', '45'];
        $womenSizes = ['36', '37', '38', '39', '40', '41'];
        $kidsSizes = ['28', '29', '30', '31', '32', '33', '34', '35'];

        foreach ($this->products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'brand' => $productData['brand'],
                'description' => $productData['description'],
                'price' => $productData['price'],
                'original_price' => $productData['original_price'],
                'category' => $productData['category'],
                'featured' => $productData['featured'],
                'colors' => $productData['colors'],
                'color_images' => $productData['color_images'],
                'tags' => $productData['tags'],
                'model_3d_url' => $productData['model_3d_url'],
                'images' => $productData['images'],
                'active' => true,
            ]);

            $sizes = match($productData['category']) {
                'Hombre' => $menSizes,
                'Mujer' => $womenSizes,
                'Niño' => $kidsSizes,
                default => $menSizes,
            };

            foreach ($productData['colors'] as $color) {
                foreach ($sizes as $size) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'size' => $size,
                        'color' => $color,
                        'stock' => rand(4, 15),
                    ]);
                }
            }
        }
    }
}
