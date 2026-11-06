<?php

namespace App\Services;

use App\Models\Inventory;

class InventoryService
{
    public function findOrCreateForShop(int $shopId, string $type): Inventory
    {
        return Inventory::firstOrCreate(
            ['shop_id' => $shopId, 'type' => $type],
            ['amount_category' => 0],
        );
    }
}
