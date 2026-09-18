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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'category' => 'required|in:Hombre,Mujer,Niño',
            'model_3d_url' => 'nullable|string',
            'images' => 'nullable|array',
            'colors' => 'nullable|array',
            'featured' => 'boolean',
            'active' => 'boolean',
        ]);

        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Producto creado correctamente',
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = is_numeric($id)
            ? Product::findOrFail($id)
            : Product::where('slug', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'brand' => 'sometimes|string',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'category' => 'sometimes|in:Hombre,Mujer,Niño',
            'model_3d_url' => 'nullable|string',
            'images' => 'nullable|array',
            'colors' => 'nullable|array',
            'featured' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'data' => $product,
        ]);
    }

    public function destroy($id)
    {
        $product = is_numeric($id)
            ? Product::findOrFail($id)
            : Product::where('slug', $id)->firstOrFail();

        $product->delete();

        return response()->json([
            'message' => 'Producto eliminado correctamente',
        ]);
    }

    public function categories()
    {
        return response()->json([
            'categories' => ['Hombre', 'Mujer', 'Niño'],
        ]);
    }
}
