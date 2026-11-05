<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['user_id', 'checkout_key', 'status', 'recipient', 'phone', 'address', 'city', 'postal_code', 'total'];

    protected $casts = ['total' => 'decimal:2'];

    protected $hidden = ['checkout_key'];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
