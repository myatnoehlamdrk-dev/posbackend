<?php

namespace App\Repositories\Contracts;

use App\Models\PurchaseItem;
use Illuminate\Contracts\Pagination\Paginator;

interface PurchaseItemRepositoryInterface
{
    public function list(int $shopId, ?string $status = null, int $perPage = 10): Paginator;
    public function create(array $data, ?int $userId, ?int $createdBy = null, ?int $shopId = null): PurchaseItem;
    public function update(array $data, PurchaseItem $purchaseItem): PurchaseItem;
    public function delete(PurchaseItem $purchaseItem): bool;
}
