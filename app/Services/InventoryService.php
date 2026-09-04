<?php

namespace App\Services;

use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryService
{
    public function findOrCreateForShop(int $shopId, string $type): Inventory
    {
        return Inventory::firstOrCreate(
            ['shop_id' => $shopId, 'type' => $type],
            ['amount_category' => 25]
        );
    }

    public function listForShop(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Inventory::where('shop_id', $user->shop_id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return response()->json(InventoryResource::collection($query->latest()->paginate(20)));
    }

    public function create(int $shopId, string $type): JsonResponse
    {
        $inventory = $this->findOrCreateForShop($shopId, $type);

        return response()->json(new InventoryResource($inventory), 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return response()->json(new InventoryResource($inventory));
    }

    public function update(array $data, Inventory $inventory): JsonResponse
    {
        $inventory->update([
            'shop_id' => $data['shopId'] ?? $inventory->shop_id,
            'type' => $data['type'] ?? $inventory->type,
            'amount_category' => $data['amountCategory'] ?? $inventory->amount_category,
        ]);

        return response()->json(new InventoryResource($inventory));
    }

    public function delete(Inventory $inventory): JsonResponse
    {
        $inventory->delete();

        return response()->json(['message' => 'Inventory deleted successfully.']);
    }
}
