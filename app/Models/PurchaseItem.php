<?php

namespace App\Models;

use App\Services\DashboardService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_id',
        'supplier_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'total_price',
        'date',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'total_price' => 'integer',
    ];

    protected static function booted(): void
    {
        // A receipt linked to a product changes the stock that product held at
        // the end of last month, which the dashboard's low-stock comparison
        // reconstructs and caches.
        static::created(fn () => DashboardService::bust());
        static::updated(fn () => DashboardService::bust());
        static::deleted(fn () => DashboardService::bust());
    }

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

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
