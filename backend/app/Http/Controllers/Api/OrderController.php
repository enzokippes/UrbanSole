<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->orders()->with('items')->latest('id')->paginate(10);
    }

    public function show(Request $request, int $id)
    {
        return $request->user()->orders()->with('items')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'checkout_key' => 'required|uuid',
            'recipient' => 'required|string|max:255', 'phone' => 'required|string|max:40',
            'address' => 'required|string|max:255', 'city' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer',
            'items.*.size' => 'required|string|max:255',
            'items.*.color' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:100',
        ]);
        $order = DB::transaction(function () use ($request, $data) {
            // Serialize checkouts for this user and make retries safe.
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $previous = Order::where('checkout_key', $data['checkout_key'])->first();
            if ($previous) {
                abort_unless($previous->user_id === $request->user()->id, 409);

                return $previous->load('items');
            }
            $lines = [];
            foreach ($data['items'] as $item) {
                $key = json_encode([$item['product_id'], $item['size'], $item['color']]);
                if (! isset($lines[$key])) {
                    $lines[$key] = $item;
                } else {
                    $lines[$key]['quantity'] += $item['quantity'];
                }
            }
            // Consistent product lock order also coordinates catalog writes.
            $products = Product::whereIn('id', array_column($lines, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $snapshots = [];
            $totalCents = 0;
            foreach ($lines as $item) {
                $product = $products->get($item['product_id']);
                $variant = ProductVariant::where('product_id', $item['product_id'])->where('size', $item['size'])->where('color', $item['color'])->orderBy('id')->lockForUpdate()->first();
                if (! $product || ! $product->active || ! $variant || $variant->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => 'Un producto ya no está disponible o no tiene stock suficiente. Revisá tu carrito.']);
                }
                $cents = (int) round((float) $product->price * 100);
                $totalCents += $cents * $item['quantity'];
                $variant->decrement('stock', $item['quantity']);
                $snapshots[] = ['product_variant_id' => $variant->id, 'product_name' => $product->name, 'size' => $variant->size, 'color' => $variant->color, 'quantity' => $item['quantity'], 'unit_price' => $product->price];
            }
            $order = Order::create(array_merge(collect($data)->except('items')->all(), ['user_id' => $request->user()->id, 'total' => $totalCents / 100]));
            $order->items()->createMany($snapshots);

            return $order->load('items');
        }, 3);

        return response()->json($order, 201);
    }
}
