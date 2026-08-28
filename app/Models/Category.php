<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'name',
        'amount_of_package',
        'package_limit',
        'description',
    ];

    protected $casts = [
        'amount_of_package' => 'integer',
        'package_limit' => 'integer',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }
}
