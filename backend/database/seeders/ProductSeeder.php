<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    private array $products = [
        // ─── CATEGORÍA: HOMBRE (4 MODELOS) ─────────────────────────────────────────
        [
            'name' => 'Air Jordan 1 Retro High OG "Chicago"',
            'brand' => 'Nike',
            'description' => 'El ícono legendario de 1985 con la mítica combinación Chicago (Rojo, Blanco y Negro). Silueta de caña alta en cuero premium con amortiguación Air encapsulada y modelo 3D fiel al diseño original.',
            'price' => 185.00,
            'original_price' => 215.00,
            'category' => 'Hombre',
            'featured' => true,
            'colors' => [
                'Chicago (Rojo/Blanco/Negro)',
                'Gym Bred (Rojo/Negro)',
                'Black Toe (Blanco/Negro/Rojo)',
            ],
            'color_images' => [
                'Chicago (Rojo/Blanco/Negro)'  => '/images/jordan-chicago.jpg',
                'Gym Bred (Rojo/Negro)'        => '/images/jordan-bred.jpg',
                'Black Toe (Blanco/Negro/Rojo)'=> '/images/jordan-royal.jpg',
            ],
            'color_models' => [
                'Chicago (Rojo/Blanco/Negro)'  => '/models/jordan-chicago.glb',
                'Gym Bred (Rojo/Negro)'        => '/models/jordan-bred.glb',
                'Black Toe (Blanco/Negro/Rojo)'=> '/models/jordan-royal.glb',
            ],
            'tags' => ['jordan', 'basketball', 'retro', 'high-top', '3d'],
            'model_3d_url' => '/models/jordan-chicago.glb',
            'images' => [
                '/images/jordan-chicago.jpg',
                '/images/jordan-bred.jpg',
                '/images/jordan-royal.jpg',
            ],
        ],
        [
            'name' => 'Nike Air Force 1 Low "Carhartt Mocha"',
            'brand' => 'Nike',
            'description' => 'Edición urbana en lona utilitaria marrón Mocha y gamuza con interior térmico oscuro, ojales metálicos dorados y entresuela Air en blanco marfil.',
            'price' => 170.00,
            'original_price' => 195.00,
            'category' => 'Hombre',
            'featured' => true,
            'colors' => [
                'Carhartt Mocha (Beige/Marrón/Blanco)',
                'Ice Smoke (Blanco/Gris)',
                'Chicago Classic (Rojo/Blanco)',
            ],
            'color_images' => [
                'Carhartt Mocha (Beige/Marrón/Blanco)' => '/images/jordan-mocha.jpg',
                'Ice Smoke (Blanco/Gris)'              => '/images/jordan-smoke-grey.jpg',
                'Chicago Classic (Rojo/Blanco)'        => '/images/jordan-chicago.jpg',
            ],
            'color_models' => [
                'Carhartt Mocha (Beige/Marrón/Blanco)' => '/models/af1-mocha.glb',
                'Ice Smoke (Blanco/Gris)'              => '/models/jordan-smoke-grey.glb',
                'Chicago Classic (Rojo/Blanco)'        => '/models/jordan-chicago.glb',
            ],
            'tags' => ['air-force-1', 'mocha', 'streetwear', 'low-top', '3d'],
            'model_3d_url' => '/models/af1-mocha.glb',
            'images' => [
                '/images/jordan-mocha.jpg',
                '/images/jordan-smoke-grey.jpg',
                '/images/jordan-chicago.jpg',
            ],
        ],
        [
            'name' => 'Nike Air Max 90 "Hyper Royal"',
            'brand' => 'Nike',
            'description' => 'Diseño aerodinámico en Azul Hyper Royal con Swoosh en contraste blanco luminoso y unidad Air Max visible en el talón para absorción de impacto superior.',
            'price' => 160.00,
            'original_price' => null,
            'category' => 'Hombre',
            'featured' => true,
            'colors' => [
                'Hyper Royal (Azul/Blanco)',
                'Crimson Street (Negro/Rojo)',
                'Citrus Air (Blanco/Rojo)',
            ],
            'color_images' => [
                'Hyper Royal (Azul/Blanco)'   => '/images/velocity-blue.jpg',
                'Crimson Street (Negro/Rojo)' => '/images/jordan-bred.jpg',
                'Citrus Air (Blanco/Rojo)'    => '/images/dunk-coast.jpg',
            ],
            'color_models' => [
                'Hyper Royal (Azul/Blanco)'   => '/models/velocity-midnight.glb',
                'Crimson Street (Negro/Rojo)' => '/models/velocity-street.glb',
                'Citrus Air (Blanco/Rojo)'    => '/models/airmax-orange.glb',
            ],
            'tags' => ['air-max', 'running', 'royal-blue', 'performance', '3d'],
            'model_3d_url' => '/models/velocity-midnight.glb',
            'images' => [
                '/images/velocity-blue.jpg',
                '/images/jordan-bred.jpg',
                '/images/dunk-coast.jpg',
            ],
        ],
        [
            'name' => 'New Balance 247 "Olive Tactical"',
            'brand' => 'New Balance',
            'description' => 'Construcción tipo botín en malla respirable y neopreno Verde Oliva militar con logo N en negro mate y entresuela REVlite ultraligera.',
            'price' => 145.00,
            'original_price' => 165.00,
            'category' => 'Hombre',
            'featured' => false,
            'colors' => [
                'Olive Tactical (Verde/Negro/Blanco)',
                'Cyber Grid (Gris/Verde)',
                'Midnight Runner (Azul/Negro)',
            ],
            'color_images' => [
                'Olive Tactical (Verde/Negro/Blanco)' => '/images/velocity-black.jpg',
                'Cyber Grid (Gris/Verde)'             => '/images/velocity-coral.jpg',
                'Midnight Runner (Azul/Negro)'        => '/images/velocity-blue.jpg',
            ],
            'color_models' => [
                'Olive Tactical (Verde/Negro/Blanco)' => '/models/nb-olive.glb',
                'Cyber Grid (Gris/Verde)'             => '/models/runner-grey-volt.glb',
                'Midnight Runner (Azul/Negro)'        => '/models/velocity-midnight.glb',
            ],
            'tags' => ['new-balance', 'olive', 'running', 'tactical', '3d'],
            'model_3d_url' => '/models/nb-olive.glb',
            'images' => [
                '/images/velocity-black.jpg',
                '/images/velocity-coral.jpg',
                '/images/velocity-blue.jpg',
            ],
        ],

        // ─── CATEGORÍA: MUJER (4 MODELOS) ──────────────────────────────────────────
        [
            'name' => 'Nike Air Force 1 Shadow "Pastel Dream"',
            'brand' => 'Nike',
            'description' => 'Silueta de doble capa diseñada especialmente para mujer con paneles en Rosa Pastel, Swoosh Lavanda, puntera Amarillo Suave y detalles celestes sobre plataforma Air.',
            'price' => 155.00,
            'original_price' => 180.00,
            'category' => 'Mujer',
            'featured' => true,
            'colors' => [
                'Pastel Dream (Rosa/Azul/Blanco)',
                'Rose Quartz (Rosa/Gris)',
                'Ice White (Blanco/Gris)',
            ],
            'color_images' => [
                'Pastel Dream (Rosa/Azul/Blanco)' => '/images/precision-low.jpg',
                'Rose Quartz (Rosa/Gris)'         => '/images/dunk-coast.jpg',
                'Ice White (Blanco/Gris)'         => '/images/jordan-smoke-grey.jpg',
            ],
            'color_models' => [
                'Pastel Dream (Rosa/Azul/Blanco)' => '/models/af1-pastel-shadow.glb',
                'Rose Quartz (Rosa/Gris)'         => '/models/velocity-beach.glb',
                'Ice White (Blanco/Gris)'         => '/models/jordan-smoke-grey.glb',
            ],
            'tags' => ['af1-shadow', 'pastel', 'women', 'platform', '3d'],
            'model_3d_url' => '/models/af1-pastel-shadow.glb',
            'images' => [
                '/images/precision-low.jpg',
                '/images/dunk-coast.jpg',
                '/images/jordan-smoke-grey.jpg',
            ],
        ],
        [
            'name' => 'Nike Air Velocity "Rose Quartz" W',
            'brand' => 'Nike',
            'description' => 'Zapatilla de running y entrenamiento femenino con capellada tejida en Rosa Cuarzo y Gris Carbón. Suela anatómica ultraliviana de alto retorno de energía.',
            'price' => 140.00,
            'original_price' => 160.00,
            'category' => 'Mujer',
            'featured' => true,
            'colors' => [
                'Rose Quartz (Rosa/Gris/Blanco)',
                'Atmosphere Pink (Rosa/Blanco)',
                'Hyper Blue (Azul/Blanco)',
            ],
            'color_images' => [
                'Rose Quartz (Rosa/Gris/Blanco)' => '/images/precision-low.jpg',
                'Atmosphere Pink (Rosa/Blanco)'  => '/images/dunk-coast.jpg',
                'Hyper Blue (Azul/Blanco)'       => '/images/velocity-blue.jpg',
            ],
            'color_models' => [
                'Rose Quartz (Rosa/Gris/Blanco)' => '/models/velocity-beach.glb',
                'Atmosphere Pink (Rosa/Blanco)'  => '/models/jordan-atmosphere-pink.glb',
                'Hyper Blue (Azul/Blanco)'       => '/models/velocity-midnight.glb',
            ],
            'tags' => ['running', 'rose-quartz', 'women', 'fitness', '3d'],
            'model_3d_url' => '/models/velocity-beach.glb',
            'images' => [
                '/images/precision-low.jpg',
                '/images/dunk-coast.jpg',
                '/images/velocity-blue.jpg',
            ],
        ],
        [
            'name' => 'Air Jordan 1 High "Ice White & Chrome"',
            'brand' => 'Nike',
            'description' => 'Estética minimalista y sofisticada en cuero Blanco Hielo con textura reptil en el Swoosh, correa de tobillo desmontable e interior acolchado color camel.',
            'price' => 175.00,
            'original_price' => 195.00,
            'category' => 'Mujer',
            'featured' => true,
            'colors' => [
                'Ice White (Blanco/Gris/Beige)',
                'Atmosphere Rose (Rosa/Blanco)',
                'Mocha Luxe (Beige/Blanco)',
            ],
            'color_images' => [
                'Ice White (Blanco/Gris/Beige)' => '/images/jordan-smoke-grey.jpg',
                'Atmosphere Rose (Rosa/Blanco)' => '/images/precision-low.jpg',
                'Mocha Luxe (Beige/Blanco)'     => '/images/jordan-mocha.jpg',
            ],
            'color_models' => [
                'Ice White (Blanco/Gris/Beige)' => '/models/jordan-smoke-grey.glb',
                'Atmosphere Rose (Rosa/Blanco)' => '/models/jordan-atmosphere-pink.glb',
                'Mocha Luxe (Beige/Blanco)'     => '/models/af1-mocha.glb',
            ],
            'tags' => ['jordan', 'ice-white', 'women', 'high-top', '3d'],
            'model_3d_url' => '/models/jordan-smoke-grey.glb',
            'images' => [
                '/images/jordan-smoke-grey.jpg',
                '/images/precision-low.jpg',
                '/images/jordan-mocha.jpg',
            ],
        ],
        [
            'name' => 'UrbanSole RS-X "Sunset Prism"',
            'brand' => 'UrbanSole',
            'description' => 'Silueta chunky retro-futurista con base en crema marfil y paneles vibrantes en Verde Agua, Naranja Atardecer, Violeta profundo y cordones Carmesí.',
            'price' => 150.00,
            'original_price' => null,
            'category' => 'Mujer',
            'featured' => false,
            'colors' => [
                'Sunset Prism (Beige/Verde/Rojo)',
                'Pastel Shadow (Rosa/Blanco)',
                'Citrus Coral (Blanco/Rojo)',
            ],
            'color_images' => [
                'Sunset Prism (Beige/Verde/Rojo)' => '/images/jordan-sunset.jpg',
                'Pastel Shadow (Rosa/Blanco)'     => '/images/precision-low.jpg',
                'Citrus Coral (Blanco/Rojo)'      => '/images/dunk-coast.jpg',
            ],
            'color_models' => [
                'Sunset Prism (Beige/Verde/Rojo)' => '/models/sunset-chunky.glb',
                'Pastel Shadow (Rosa/Blanco)'     => '/models/af1-pastel-shadow.glb',
                'Citrus Coral (Blanco/Rojo)'      => '/models/airmax-orange.glb',
            ],
            'tags' => ['chunky', 'sunset', 'multicolor', 'women', '3d'],
            'model_3d_url' => '/models/sunset-chunky.glb',
            'images' => [
                '/images/jordan-sunset.jpg',
                '/images/precision-low.jpg',
                '/images/dunk-coast.jpg',
            ],
        ],

        // ─── CATEGORÍA: NIÑO (4 MODELOS) ───────────────────────────────────────────
        [
            'name' => 'Air Jordan 1 High OG Kids "Black Toe Red"',
            'brand' => 'Nike',
            'description' => 'Toda la historia de Jordan adaptada para niños. Puntera y Swoosh en negro, paneles blancos de cuero fácil de limpiar y talón en Rojo Fuego con el logo Wings.',
            'price' => 95.00,
            'original_price' => 110.00,
            'category' => 'Niño',
            'featured' => true,
            'colors' => [
                'Black Toe (Blanco/Negro/Rojo)',
                'University Sky (Azul/Blanco)',
                'Chicago Kids (Rojo/Blanco)',
            ],
            'color_images' => [
                'Black Toe (Blanco/Negro/Rojo)' => '/images/jordan-royal.jpg',
                'University Sky (Azul/Blanco)'  => '/images/velocity-blue.jpg',
                'Chicago Kids (Rojo/Blanco)'    => '/images/jordan-chicago.jpg',
            ],
            'color_models' => [
                'Black Toe (Blanco/Negro/Rojo)' => '/models/jordan-royal.glb',
                'University Sky (Azul/Blanco)'  => '/models/jordan-kids-sky.glb',
                'Chicago Kids (Rojo/Blanco)'    => '/models/jordan-chicago.glb',
            ],
            'tags' => ['kids', 'jordan', 'basketball', 'high-top', '3d'],
            'model_3d_url' => '/models/jordan-royal.glb',
            'images' => [
                '/images/jordan-royal.jpg',
                '/images/velocity-blue.jpg',
                '/images/jordan-chicago.jpg',
            ],
        ],
        [
            'name' => 'Nike Air Max 1 Kids "Citrus Orange"',
            'brand' => 'Nike',
            'description' => 'Clásico liviano y resistente para todos los días. Capellada microperforada blanca con guardabarros y Swoosh en Naranja Cítrico y cámara de aire infantil.',
            'price' => 85.00,
            'original_price' => 98.00,
            'category' => 'Niño',
            'featured' => true,
            'colors' => [
                'Citrus Orange (Blanco/Rojo)',
                'Hyper Blue (Azul/Blanco)',
                'Pastel Kids (Rosa/Blanco)',
            ],
            'color_images' => [
                'Citrus Orange (Blanco/Rojo)' => '/images/dunk-coast.jpg',
                'Hyper Blue (Azul/Blanco)'    => '/images/velocity-blue.jpg',
                'Pastel Kids (Rosa/Blanco)'   => '/images/precision-low.jpg',
            ],
            'color_models' => [
                'Citrus Orange (Blanco/Rojo)' => '/models/airmax-orange.glb',
                'Hyper Blue (Azul/Blanco)'    => '/models/velocity-midnight.glb',
                'Pastel Kids (Rosa/Blanco)'   => '/models/af1-pastel-shadow.glb',
            ],
            'tags' => ['kids', 'air-max-1', 'citrus', 'comfort', '3d'],
            'model_3d_url' => '/models/airmax-orange.glb',
            'images' => [
                '/images/dunk-coast.jpg',
                '/images/velocity-blue.jpg',
                '/images/precision-low.jpg',
            ],
        ],
        [
            'name' => 'Vans Old Skool Kids "Skate Burgundy"',
            'brand' => 'Vans',
            'description' => 'La legendaria zapatilla de skate para niños en gamuza y lona Bordó con la icónica franja lateral blanca Sidestripe, puntera reforzada y suela Waffle antideslizante.',
            'price' => 75.00,
            'original_price' => null,
            'category' => 'Niño',
            'featured' => false,
            'colors' => [
                'Skate Burgundy (Rojo/Blanco)',
                'Vintage Court (Azul/Rojo/Beige)',
                'Forest Olive (Verde/Blanco)',
            ],
            'color_images' => [
                'Skate Burgundy (Rojo/Blanco)'    => '/images/canvas-retro.jpg',
                'Vintage Court (Azul/Rojo/Beige)' => '/images/jordan-sunset.jpg',
                'Forest Olive (Verde/Blanco)'     => '/images/velocity-black.jpg',
            ],
            'color_models' => [
                'Skate Burgundy (Rojo/Blanco)'    => '/models/vans-burgundy.glb',
                'Vintage Court (Azul/Rojo/Beige)' => '/models/rec-shoe.glb',
                'Forest Olive (Verde/Blanco)'     => '/models/nb-olive.glb',
            ],
            'tags' => ['kids', 'vans', 'skate', 'canvas', '3d'],
            'model_3d_url' => '/models/vans-burgundy.glb',
            'images' => [
                '/images/canvas-retro.jpg',
                '/images/jordan-sunset.jpg',
                '/images/velocity-black.jpg',
            ],
        ],
        [
            'name' => 'Adidas Deerupt Kids "Cyber Grid Volt"',
            'brand' => 'Adidas',
            'description' => 'Zapatilla ultramoderna para chicos con red elástica envolvente en Gris Urbano y destellos en Verde Neón Volt. Calce tipo media súper cómodo y flexible.',
            'price' => 80.00,
            'original_price' => 92.00,
            'category' => 'Niño',
            'featured' => true,
            'colors' => [
                'Cyber Volt (Gris/Verde)',
                'Electric Blue (Azul/Negro)',
                'Crimson Core (Negro/Rojo)',
            ],
            'color_images' => [
                'Cyber Volt (Gris/Verde)'    => '/images/velocity-coral.jpg',
                'Electric Blue (Azul/Negro)' => '/images/velocity-blue.jpg',
                'Crimson Core (Negro/Rojo)'  => '/images/jordan-bred.jpg',
            ],
            'color_models' => [
                'Cyber Volt (Gris/Verde)'    => '/models/runner-grey-volt.glb',
                'Electric Blue (Azul/Negro)' => '/models/velocity-midnight.glb',
                'Crimson Core (Negro/Rojo)'  => '/models/velocity-street.glb',
            ],
            'tags' => ['kids', 'adidas', 'deerupt', 'volt', '3d'],
            'model_3d_url' => '/models/runner-grey-volt.glb',
            'images' => [
                '/images/velocity-coral.jpg',
                '/images/velocity-blue.jpg',
                '/images/jordan-bred.jpg',
            ],
        ],
    ];

    public function run(): void
    {
        $menSizes = ['39', '40', '41', '42', '43', '44', '45'];
        $womenSizes = ['35', '36', '37', '38', '39', '40', '41'];
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
                'color_models' => $productData['color_models'] ?? null,
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
