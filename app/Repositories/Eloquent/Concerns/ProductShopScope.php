<?php

namespace App\Repositories\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ProductShopScope
{
    /**
     * Products with no package have no inventory chain of their own, so they are
     * attributed to the shop of the user who created them. Without this gate a
     * package-less product (quick add never sends a packageId) is visible to
     * every shop.
     */
    public static function applyOrphan(Builder $query, int $shopId): void
    {
        $query->whereNull('products.package_id')
            ->whereExists(function ($sub) use ($shopId) {
                $sub->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'products.created_by')
                    ->where('users.shop_id', $shopId);
            });
    }
}
