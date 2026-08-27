<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_set',
        'name',
        'image',
        'stock',
        'size',
        'brand',
        'color',
        'sku',
        'supplier_id',
        'package_id',
    ];

    protected $casts = [
        'is_set' => 'boolean',
        'stock' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
