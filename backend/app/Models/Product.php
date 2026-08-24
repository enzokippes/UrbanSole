<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'brand',
        'description',
        'price',
        'original_price',
        'category',
        'model_3d_url',
        'images',
        'color_images',
        'colors',
        'tags',
        'featured',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'images' => 'array',
        'color_images' => 'array',
        'colors' => 'array',
        'tags' => 'array',
        'featured' => 'boolean',
        'active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function availableSizes(): array
    {
        return $this->variants()
            ->where('stock', '>', 0)
            ->pluck('size')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    public function availableColors(): array
    {
        return $this->variants()
            ->where('stock', '>', 0)
            ->pluck('color')
            ->unique()
            ->values()
            ->toArray();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }
}
