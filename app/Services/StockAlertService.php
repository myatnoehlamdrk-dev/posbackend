<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class StockAlertService
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

    public function checkAndNotify(int $shopId): array
    {
        $triggered = $this->getTriggeredAlerts($shopId);

        $notifications = $triggered->map(fn (StockAlert $alert) => [
            'product_id' => $alert->product_id,
            'product_name' => $alert->product->name,
            'current_stock' => $alert->product->getAvailableStock(),
            'threshold' => $alert->threshold,
        ])->toArray();

        StockAlert::whereIn('id', $triggered->pluck('id'))
            ->update(['last_notified_at' => now()]);

        return $notifications;
    }
}
