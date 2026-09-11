<?php

namespace App\Repositories\Eloquent;

use App\Models\Inventory;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentInventoryRepository implements InventoryRepositoryInterface
{
    public function findOrCreateForShop(int $shopId, string $type): Inventory
    {
        return Inventory::firstOrCreate(
            ['shop_id' => $shopId, 'type' => $type],
            ['amount_category' => 25]
        );
    }

    public function listForShop(int $shopId, ?string $type = null): LengthAwarePaginator
    {
        $query = Inventory::where('shop_id', $shopId);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->latest()->paginate(20);
    }

    public function create(int $shopId, string $type): Inventory
    {
        return $this->findOrCreateForShop($shopId, $type);
    }

    public function update(array $data, Inventory $inventory): Inventory
    {
        $inventory->update([
            'shop_id' => $data['shopId'] ?? $inventory->shop_id,
            'type' => $data['type'] ?? $inventory->type,
            'amount_category' => $data['amountCategory'] ?? $inventory->amount_category,
        ]);

        return $inventory->fresh();
    }

    public function delete(Inventory $inventory): bool
    {
        return $inventory->delete();
    }
}
