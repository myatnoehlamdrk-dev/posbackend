<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'supplier_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'total_price',
        'date',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'total_price' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
