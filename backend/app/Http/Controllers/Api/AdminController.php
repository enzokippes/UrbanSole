<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // ─── Products ─────────────────────────────────────────────

    public function productIndex(Request $request)
    {
        $query = Product::with('variants');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $products = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($products);
    }

    public function productShow($id)
    {
        $product = Product::with('variants')->findOrFail($id);
        return response()->json($product);
    }

    public function productStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'category' => 'required|in:Hombre,Mujer,Niño',
            'model_3d_url' => 'nullable|url',
            'images' => 'nullable|array',
            'colors' => 'nullable|array',
            'featured' => 'boolean',
            'active' => 'boolean',
        ]);

        $product = Product::create([
            ...$request->only([
                'name', 'brand', 'description', 'price', 'original_price',
                'category', 'model_3d_url', 'images', 'colors', 'tags', 'featured', 'active',
            ]),
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($product->load('variants'), 201);
    }

    public function productUpdate(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|in:Hombre,Mujer,Niño',
            'featured' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
        ]);

        $data = $request->only([
            'name', 'brand', 'description', 'price', 'original_price',
            'category', 'model_3d_url', 'images', 'colors', 'tags', 'featured', 'active',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $product->update($data);

        return response()->json($product->fresh('variants'));
    }

    public function productDestroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    // ─── Orders ───────────────────────────────────────────────

    public function orderIndex(Request $request)
    {
        $orders = Order::with(['user', 'items'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($orders);
    }

    public function orderUpdate(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,shipped,delivered,cancelled',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        return response()->json($order);
    }

    // ─── Stats ────────────────────────────────────────────────

    public function stats()
    {
        return response()->json([
            'total_products' => Product::count(),
            'active_products' => Product::where('active', true)->count(),
            'total_orders' => Order::count(),
            'total_users' => User::count(),
            'revenue' => Order::whereNotIn('status', ['cancelled'])->sum('total'),
            'recent_orders' => Order::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ]);
    }
}
