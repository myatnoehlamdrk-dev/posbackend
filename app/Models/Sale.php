<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'voucher_no',
        'product_id',
        'product_name',
        'order_id',
        'quantity_sold',
        'total_price',
        'price_per_unit',
        'customer_name',
        'pay_method',
    ];

    protected $casts = [
        'quantity_sold' => 'integer',
        'total_price' => 'integer',
        'price_per_unit' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
