<?php

namespace App\Repositories\Contracts;

use App\Models\PurchaseItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseItemRepositoryInterface
{
    public function list(int $shopId, ?string $status = null, int $perPage = 10): LengthAwarePaginator;
    public function create(array $data, ?int $userId, ?int $createdBy = null): PurchaseItem;
    public function update(array $data, PurchaseItem $purchaseItem): PurchaseItem;
    public function delete(PurchaseItem $purchaseItem): bool;
}
