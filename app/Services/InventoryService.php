<?php

namespace App\Services;

use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryService
{
    public function __construct(
        protected InventoryRepositoryInterface $inventoryRepository,
    ) {}

    public function findOrCreateForShop(int $shopId, string $type): Inventory
    {
        return $this->inventoryRepository->findOrCreateForShop($shopId, $type);
    }

    public function listForShop(Request $request): JsonResponse
    {
        $user = $request->user();
        $inventory = $this->inventoryRepository->listForShop($user->shop_id, $request->input('type'));

        return response()->json(InventoryResource::collection($inventory));
    }

    public function create(int $shopId, string $type): JsonResponse
    {
        $inventory = $this->inventoryRepository->create($shopId, $type);

        return response()->json(new InventoryResource($inventory), 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return response()->json(new InventoryResource($inventory));
    }

    public function update(array $data, Inventory $inventory): JsonResponse
    {
        $updated = $this->inventoryRepository->update($data, $inventory);

        return response()->json(new InventoryResource($updated));
    }

    public function delete(Inventory $inventory): JsonResponse
    {
        $this->inventoryRepository->delete($inventory);

        return response()->json(['message' => 'Inventory deleted successfully.']);
    }
}
