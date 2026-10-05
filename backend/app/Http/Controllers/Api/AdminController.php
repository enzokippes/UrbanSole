<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function stats()
    {
        $sales = Order::whereIn('status', ['confirmed', 'shipped', 'delivered']);

        return response()->json([
            'total_users' => User::count(), 'total_products' => Product::count(),
            'total_orders' => Order::count(), 'pending_orders' => Order::where('status', 'pending')->count(),
            'sales_total' => (clone $sales)->sum('total'), 'average_order' => (clone $sales)->avg('total') ?? 0,
            'low_stock_variants' => ProductVariant::where('stock', '<=', 3)->count(),
            'orders_by_status' => Order::selectRaw('status, COUNT(*) as count')->groupBy('status')->get(),
            'message' => 'Admin stats access granted',
        ]);
    }

    public function products()
    {
        return Product::with('variants')->latest('id')->paginate(20);
    }

    public function storeProduct(Request $request)
    {
        return $this->saveProduct($request, null);
    }

    public function updateProduct(Request $request, int $id)
    {
        return $this->saveProduct($request, $id);
    }

    private function saveProduct(Request $request, ?int $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255', 'brand' => 'required|string|max:255',
            'description' => 'required|string', 'category' => ['required', Rule::in(['Hombre', 'Mujer', 'Niño'])],
            'price' => 'required|numeric|min:0.01|max:99999999.99', 'original_price' => 'nullable|numeric|min:0|max:99999999.99',
            'active' => 'required|boolean', 'featured' => 'required|boolean',
            'images' => 'nullable|array', 'images.*' => 'string|max:2048',
            'color_images' => 'nullable|array', 'color_images.*' => 'string|max:2048',
            'model_3d_url' => 'nullable|string|max:255', 'tags' => 'nullable|array', 'tags.*' => 'string|max:255',
            'variants' => 'required|array|min:1|max:200', 'variants.*.id' => 'nullable|integer',
            'variants.*.size' => 'required|string|max:255', 'variants.*.color' => 'required|string|max:255',
            'variants.*.stock' => 'required|integer|min:0|max:1000000',
        ]);
        $product = DB::transaction(function () use ($data, $id) {
            $product = $id ? Product::whereKey($id)->lockForUpdate()->firstOrFail() : new Product;
            $existing = $id ? $product->variants()->orderBy('id')->lockForUpdate()->get()->keyBy('id') : collect();
            $pairs = [];
            $retained = [];
            foreach ($data['variants'] as $variant) {
                $pair = json_encode([$variant['size'], $variant['color']]);
                if (isset($pairs[$pair])) {
                    throw ValidationException::withMessages(['variants' => 'Hay variantes repetidas.']);
                }
                $pairs[$pair] = true;
                if (! empty($variant['id'])) {
                    if (! $existing->has($variant['id']) || in_array($variant['id'], $retained)) {
                        throw ValidationException::withMessages(['variants' => 'La variante no pertenece a este producto o está repetida.']);
                    }
                    $old = $existing->get($variant['id']);
                    if (($old->size !== $variant['size'] || $old->color !== $variant['color']) && OrderItem::where('product_variant_id', $old->id)->exists()) {
                        throw ValidationException::withMessages(['variants' => 'Una variante con pedidos conserva su talle y color. Creá una variante nueva.']);
                    }
                    $retained[] = $variant['id'];
                }
            }
            $removed = $existing->keys()->diff($retained);
            if (OrderItem::whereIn('product_variant_id', $removed)->exists()) {
                throw ValidationException::withMessages(['variants' => 'No se puede quitar una variante con pedidos. Conservála y ajustá su stock.']);
            }
            $product->fill(collect($data)->except('variants')->all());
            if (! $id) {
                $product->slug = Str::slug($data['name']).'-'.Str::lower(Str::random(8));
            }
            $product->colors = array_values(array_unique(array_column($data['variants'], 'color')));
            $product->save();
            foreach ($data['variants'] as $variant) {
                $values = collect($variant)->except('id')->all();
                if (! empty($variant['id'])) {
                    $existing->get($variant['id'])->update($values);
                } else {
                    $product->variants()->create($values);
                }
            }
            $product->variants()->whereIn('id', $removed)->delete();

            return $product->load('variants');
        }, 3);

        return response()->json($product, $id ? 200 : 201);
    }

    public function destroyProduct(int $id)
    {
        // Preserve purchased products and their history; remove from the public catalog.
        DB::transaction(function () use ($id) {
            Product::whereKey($id)->lockForUpdate()->firstOrFail()->update(['active' => false]);
        });

        return response()->json(['message' => 'Producto desactivado.']);
    }

    public function orders()
    {
        return Order::with(['items', 'user:id,name,email'])->latest('id')->paginate(20);
    }

    public function updateOrder(Request $request, int $id)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])]]);

        return DB::transaction(function () use ($data, $id) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            $transitions = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => [], 'cancelled' => []];
            if ($data['status'] === $order->status) {
                return $order->load('items');
            }
            if (! in_array($data['status'], $transitions[$order->status])) {
                throw ValidationException::withMessages(['status' => 'El cambio de estado no está permitido.']);
            }
            if ($data['status'] === 'cancelled') {
                $items = $order->items()->orderBy('product_variant_id')->get();
                foreach ($items as $item) {
                    if ($item->product_variant_id) {
                        ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first()?->increment('stock', $item->quantity);
                    }
                }
            }
            $order->update($data);

            return $order->load('items');
        }, 3);
    }
}
