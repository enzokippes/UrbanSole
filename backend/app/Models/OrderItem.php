<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['product_variant_id', 'product_name', 'size', 'color', 'quantity', 'unit_price'];

    protected $casts = ['unit_price' => 'decimal:2', 'quantity' => 'integer'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
