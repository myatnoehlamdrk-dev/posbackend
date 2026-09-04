<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

trait ShopScope
{
    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForShopViaRelation(Builder $query, string $relation, int $shopId): Builder
    {
        return $query->whereHas($relation, function ($q) use ($shopId) {
            $q->where('shop_id', $shopId);
        });
    }
}
