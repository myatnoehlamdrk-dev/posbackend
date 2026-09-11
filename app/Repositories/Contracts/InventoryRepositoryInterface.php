<?php

namespace App\Repositories\Contracts;

use App\Models\Inventory;

interface InventoryRepositoryInterface
{
    public function findOrCreateForShop(int $shopId, string $type): Inventory;
    public function listForShop(int $shopId, ?string $type = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function create(int $shopId, string $type): Inventory;
    public function update(array $data, Inventory $inventory): Inventory;
    public function delete(Inventory $inventory): bool;
}
