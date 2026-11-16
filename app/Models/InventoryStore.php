<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStore extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'created_by',
        'product_id',
        'sku',
        'universal_identifier',
        'name',
        'brand',
        'description',
        'category',
        'location_id',
        'quantity_on_hand',
        'quantity_available',
        'safety_stock',
        'reorder_point',
        'cost_price',
        'retail_price',
        'dimensions',
        'weight',
        'batch_number',
        'expiration_date',
        'best_before_date',
        'active',
    ];

    protected $casts = [
        'quantity_on_hand' => 'integer',
        'quantity_available' => 'integer',
        'safety_stock' => 'integer',
        'reorder_point' => 'integer',
        'cost_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'weight' => 'decimal:3',
        'expiration_date' => 'date',
        'best_before_date' => 'date',
        'active' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}