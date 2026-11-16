<?php

namespace App\Models;

use App\Services\DashboardService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'active',
        'category_id',
        'name',
        'amount_of_product',
        'product_limit',
        'description',
        'location',
        'stock_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount_of_product' => 'integer',
        'product_limit' => 'integer',
    ];

    protected static function booted(): void
    {
        // The dashboard counts packages (and their stock tiers) per shop, and
        // caches the result — a package add/rename/delete has to invalidate it
        // just like a product change does.
        static::created(fn () => DashboardService::bust());
        static::updated(fn () => DashboardService::bust());
        static::deleted(fn () => DashboardService::bust());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
