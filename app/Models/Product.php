<?php

namespace App\Models;

use App\Factories\StockCalculatorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_set',
        'name',
        'product_type',
        'image',
        'image_delete_url',
        'stock',
        'size',
        'brand',
        'color',
        'sku',
        'variants',
        'bundle_components',
        'supplier_id',
        'supplier_contact',
        'supplier_since',
        'supplier_address',
        'package_id',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_set' => 'boolean',
        'stock' => 'integer',
        'variants' => 'array',
        'bundle_components' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function getStockCalculatorAttribute()
    {
        return StockCalculatorFactory::make($this);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getAvailableStock(): int
    {
        return $this->stockCalculator->calculateStock($this);
    }

    public function isStockAvailable(int $quantity): bool
    {
        return $this->stockCalculator->isAvailable($this, $quantity);
    }

    public function deductStock(int $quantity, ?string $size = null, ?string $color = null): void
    {
        $this->stockCalculator->deduct($this, $quantity, $size, $color);
    }

    public function restoreStock(int $quantity, ?string $size = null, ?string $color = null): void
    {
        $this->stockCalculator->restore($this, $quantity, $size, $color);
    }
}
