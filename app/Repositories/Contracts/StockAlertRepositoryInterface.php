<?php

namespace App\Repositories\Contracts;

use App\Models\StockAlert;
use Illuminate\Support\Collection;

interface StockAlertRepositoryInterface
{
    public function listForShop(int $shopId): Collection;
    public function getTriggeredAlerts(int $shopId): Collection;
    public function upsert(int $productId, int $shopId, array $data): StockAlert;
    public function delete(int $productId, int $shopId): bool;
    public function markNotified(array $ids): void;
}
