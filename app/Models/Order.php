<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
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
        'customer_phone',
        'pay_method',
        'items',
        'grand_total',
        'discount',
        'notes',
        'status',
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
}
