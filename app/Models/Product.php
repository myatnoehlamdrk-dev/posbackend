<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'selling_price',
        'stock_quantity',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
