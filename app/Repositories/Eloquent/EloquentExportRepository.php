<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\Sale;
use App\Repositories\Contracts\ExportRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentExportRepository implements ExportRepositoryInterface
{
    public function getSalesForExport(int $shopId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = Sale::with('saleItems')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->latest();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        return $query->get();
    }

    public function getOrdersForExport(int $shopId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = Order::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))->latest();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        return $query->get();
    }
}
