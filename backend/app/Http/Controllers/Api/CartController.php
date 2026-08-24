<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cartItems = CartItem::with(['product', 'variant'])
            ->where('user_id', $request->user()->id)
            ->get();

        $subtotal = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        return response()->json([
            'items' => $cartItems,
            'subtotal' => round($subtotal, 2),
            'count' => $cartItems->sum('quantity'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        $variant = ProductVariant::findOrFail($request->product_variant_id);

        if ($variant->stock < $request->quantity) {
            return response()->json(['message' => 'Stock insuficiente'], 422);
        }

        $cartItem = CartItem::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'product_variant_id' => $request->product_variant_id,
            ],
            [
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]
        );

        return response()->json($cartItem->load(['product', 'variant']), 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        $cartItem = CartItem::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json($cartItem->load(['product', 'variant']));
    }

    public function destroy(Request $request, $id)
    {
        CartItem::where('user_id', $request->user()->id)
            ->findOrFail($id)
            ->delete();

        return response()->json(['message' => 'Item removed from cart']);
    }

    public function clear(Request $request)
    {
        CartItem::where('user_id', $request->user()->id)->delete();
        return response()->json(['message' => 'Cart cleared']);
    }
}
