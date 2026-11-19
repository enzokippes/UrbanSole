<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 30)->default('standard')->after('status');
            $table->string('payment_method', 30)->default('transfer')->after('delivery_method');
            $table->string('payment_status', 30)->default('pending')->after('payment_method');
            $table->string('payment_proof_path')->nullable()->after('payment_status');
            $table->string('payment_reference', 120)->nullable()->after('payment_proof_path');
            $table->timestamp('payment_submitted_at')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_method',
                'payment_method',
                'payment_status',
                'payment_proof_path',
                'payment_reference',
                'payment_submitted_at',
            ]);
        });
    }
};
