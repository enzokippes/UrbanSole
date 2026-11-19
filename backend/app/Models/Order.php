<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'user_order_number',
        'customer_name',
        'customer_email',
        'checkout_key',
        'status',
        'delivery_method',
        'payment_method',
        'payment_status',
        'payment_proof_path',
        'payment_reference',
        'payment_submitted_at',
        'recipient',
        'phone',
        'address',
        'city',
        'postal_code',
        'total',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'user_order_number' => 'integer',
        'payment_submitted_at' => 'datetime',
    ];

    protected $hidden = ['checkout_key', 'payment_proof_path'];

    protected $appends = ['has_payment_proof'];

    public function getHasPaymentProofAttribute(): bool
    {
        return ! empty($this->payment_proof_path);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
