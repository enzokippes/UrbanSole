<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Query Builder con filtros dinámicos multicriterio (categoría, talles con stock, colores JSON, rango de precios y ordenamiento).
     */
    public function index(Request $request)
    {
        $query = Product::with('variants')->active();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Filtro por categoria
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filtro destacados
        if ($request->filled('featured')) {
            $query->featured();
        }

        // Filtro por color (JSON contains sobre lista separada por comas)
        if ($request->filled('color')) {
            $colors = explode(',', $request->color);
            $query->where(function ($q) use ($colors) {
                foreach ($colors as $color) {
                    $q->orWhereJsonContains('colors', trim($color));
                }
            });
        }

        // Filtro por talle sobre variantes con stock disponible
        if ($request->filled('size')) {
            $sizes = explode(',', $request->size);
            $query->whereHas('variants', function ($q) use ($sizes) {
                $q->whereIn('size', $sizes)->where('stock', '>', 0);
            });
        }

        // Filtro por rango de precios (min y max)
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Ordenamiento con whitelist estricta para evitar inyeccion SQL
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['price', 'name', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $products = $query->paginate($request->get('per_page', 12));

        // Append available sizes/colors to each product
        $products->getCollection()->transform(function ($product) {
            $product->available_sizes = $product->availableSizes();
            $product->available_colors = $product->availableColors();
            return $product;
        });

        return response()->json($products);
    }

    public function show($id)
    {
        $query = Product::with('variants');
        $product = is_numeric($id)
            ? $query->findOrFail($id)
            : $query->where('slug', $id)->firstOrFail();

        $product->available_sizes = $product->availableSizes();
        $product->available_colors = $product->availableColors();

        return response()->json($product);
    }

    public function categories()
    {
        return response()->json([
            'categories' => ['Hombre', 'Mujer', 'Niño'],
        ]);
    }
}
