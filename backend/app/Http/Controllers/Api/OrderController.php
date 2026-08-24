<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'shipping_address.name' => 'required|string',
            'shipping_address.address' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.postal_code' => 'required|string',
            'shipping_address.phone' => 'required|string',
        ]);

        $cartItems = CartItem::with(['product', 'variant'])
            ->where('user_id', $request->user()->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'El carrito está vacío'], 422);
        }

        // Stock validation
        foreach ($cartItems as $item) {
            if ($item->variant->stock < $item->quantity) {
                return response()->json([
                    'message' => "Stock insuficiente para {$item->product->name}"
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            $subtotal = $cartItems->sum(fn($item) => $item->product->price * $item->quantity);
            $shipping = $subtotal > 100 ? 0 : 9.99;
            $total = $subtotal + $shipping;

            $order = Order::create([
                'user_id' => $request->user()->id,
                'status' => 'confirmed',
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $total,
                'shipping_address' => $request->shipping_address,
                'payment_method' => 'simulated',
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product->name,
                    'size' => $item->variant->size,
                    'color' => $item->variant->color,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->product->price,
                    'total_price' => $item->product->price * $item->quantity,
                ]);

                // Decrement stock
                $item->variant->decrement('stock', $item->quantity);
            }

            // Clear cart
            CartItem::where('user_id', $request->user()->id)->delete();

            DB::commit();

            return response()->json($order->load('items'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al procesar el pedido', 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $orders = Order::with('items')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    public function show(Request $request, $id)
    {
        $order = Order::with('items.product')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($order);
    }
}
