<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface InventoryStoreRepositoryInterface
{
    public function listForShop(Request $request): LengthAwarePaginator;

    public function create(array $data): \App\Models\InventoryStore;

    public function update(\App\Models\InventoryStore $row, array $data): \App\Models\InventoryStore;
}