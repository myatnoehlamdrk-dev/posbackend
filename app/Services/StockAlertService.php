<?php

namespace App\Services;

use App\Repositories\Contracts\StockAlertRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class StockAlertService
{
    public function __construct(
        protected StockAlertRepositoryInterface $stockAlertRepository,
    ) {}

    public function listForShop(int $shopId): Collection
    {
        return $this->stockAlertRepository->listForShop($shopId);
    }

    public function getTriggeredAlerts(int $shopId): Collection
    {
        return $this->stockAlertRepository->getTriggeredAlerts($shopId);
    }

    public function upsert(int $productId, int $shopId, array $data)
    {
        return $this->stockAlertRepository->upsert($productId, $shopId, $data);
    }

    public function delete(int $productId, int $shopId): bool
    {
        return $this->stockAlertRepository->delete($productId, $shopId);
    }

    public function checkAndNotify(int $shopId): array
    {
        $triggered = $this->stockAlertRepository->getTriggeredAlerts($shopId);

        $notifications = $triggered->map(fn ($alert) => [
            'product_id' => $alert->product_id,
            'product_name' => $alert->product->name,
            'current_stock' => $alert->product->getAvailableStock(),
            'threshold' => $alert->threshold,
        ])->toArray();

        $this->stockAlertRepository->markNotified($triggered->pluck('id')->toArray());

        return $notifications;
    }
}
