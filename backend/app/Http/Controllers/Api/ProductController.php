<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
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

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Featured filter
        if ($request->filled('featured')) {
            $query->featured();
        }

        // Color filter
        if ($request->filled('color')) {
            $colors = explode(',', $request->color);
            $query->where(function ($q) use ($colors) {
                foreach ($colors as $color) {
                    $q->orWhereJsonContains('colors', trim($color));
                }
            });
        }

        // Size filter (from variants)
        if ($request->filled('size')) {
            $sizes = explode(',', $request->size);
            $query->whereHas('variants', function ($q) use ($sizes) {
                $q->whereIn('size', $sizes)->where('stock', '>', 0);
            });
        }

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sorting
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

    public function show($slug)
    {
        $product = Product::with('variants')
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

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
