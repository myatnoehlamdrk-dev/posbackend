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
        'image_delete_url',
        'stock',
        'size',
        'brand',
        'color',
        'sku',
        'variants',
        'supplier_id',
        'supplier_contact',
        'supplier_since',
        'supplier_address',
        'package_id',
        'active',
    ];

    protected $casts = [
        'is_set' => 'boolean',
        'stock' => 'integer',
        'variants' => 'array',
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
