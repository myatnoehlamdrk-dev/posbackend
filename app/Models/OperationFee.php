<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationFee extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'category',
        'calculation',
        'rate',
        'is_active',
        'icon',
        'tint',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
