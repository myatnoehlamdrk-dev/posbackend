<?php

namespace App\Repositories\Eloquent;

use App\Models\StockAlert;
use App\Repositories\Contracts\StockAlertRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentStockAlertRepository implements StockAlertRepositoryInterface
{
    public function listForShop(int $shopId): Collection
    {
        return StockAlert::where('shop_id', $shopId)
            ->with('product')
            ->get();
    }

    public function getTriggeredAlerts(int $shopId): Collection
    {
        return StockAlert::where('shop_id', $shopId)
            ->where('is_active', true)
            ->with('product')
            ->get()
            ->filter(fn (StockAlert $alert) => $alert->isTriggered());
    }

    public function upsert(int $productId, int $shopId, array $data): StockAlert
    {
        return StockAlert::updateOrCreate(
            ['product_id' => $productId, 'shop_id' => $shopId],
            [
                'threshold' => $data['threshold'] ?? 5,
                'is_active' => $data['isActive'] ?? true,
            ]
        );
    }

    public function delete(int $productId, int $shopId): bool
    {
        return (bool) StockAlert::where('product_id', $productId)
            ->where('shop_id', $shopId)
            ->delete();
    }

    public function markNotified(array $ids): void
    {
        StockAlert::whereIn('id', $ids)
            ->update(['last_notified_at' => now()]);
    }
}
