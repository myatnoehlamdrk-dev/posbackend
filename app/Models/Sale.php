<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'created_by',
        'voucher_no',
        'product_id',
        'product_name',
        'order_id',
        'quantity_sold',
        'total_price',
        'price_per_unit',
        'customer_name',
        'customer_phone',
        'customer_location',
        'pay_method',
        'items',
        'grand_total',
        'discount',
        'notes',
    ];

    protected $casts = [
        'items' => 'array',
        'price_per_unit' => 'array',
        'grand_total' => 'integer',
        'discount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
