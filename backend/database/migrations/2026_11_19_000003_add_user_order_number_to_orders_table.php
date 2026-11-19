<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('user_order_number')->default(1)->after('user_id');
        });

        // Recalcular correlativo propio (1, 2, 3...) para cada usuario en los pedidos existentes
        $orders = DB::table('orders')->orderBy('id')->get(['id', 'user_id', 'customer_email']);
        $counters = [];

        foreach ($orders as $order) {
            $key = $order->user_id !== null
                ? 'u_' . $order->user_id
                : 'e_' . ($order->customer_email ?: 'order_' . $order->id);

            $counters[$key] = ($counters[$key] ?? 0) + 1;

            DB::table('orders')
                ->where('id', $order->id)
                ->update(['user_order_number' => $counters[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('user_order_number');
        });
    }
};
