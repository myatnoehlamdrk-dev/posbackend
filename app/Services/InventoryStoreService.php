<?php

namespace App\Services;

use App\Http\Resources\InventoryStoreResource;
use App\Repositories\Contracts\InventoryStoreRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryStoreService
{
    public function __construct(
        protected InventoryStoreRepositoryInterface $repository,
    ) {}

    public function adjust(\App\Models\InventoryStore $row, string $type, int $quantity, ?string $note, ?int $userId = null): \Illuminate\Http\JsonResponse
    {
        $current = (int) $row->quantity_on_hand;
        $new = $type === 'remove' ? $current - $quantity : $current + $quantity;
        if ($new < 0) {
            return response()->json(['message' => 'Insufficient stock.'], 422);
        }
        $previous = $current;
        $row = $this->repository->update($row, [
            'quantity_on_hand' => $new,
            'quantity_available' => $new,
        ]);

        \App\Models\InventoryAdjustment::create([
            'inventory_store_id' => $row->id,
            'shop_id' => $row->shop_id,
            'user_id' => $userId ?? $row->created_by,
            'adjustment_type' => $type,
            'quantity' => $quantity,
            'previous_stock' => $previous,
            'new_stock' => $new,
            'note' => $note,
        ]);

        return (new \App\Http\Resources\InventoryStoreResource($row))->response();
    }

    public function update(\App\Models\InventoryStore $row, array $data): \Illuminate\Http\JsonResponse
    {
        $row = $this->repository->update($row, $data);
        return (new \App\Http\Resources\InventoryStoreResource($row))->response();
    }

    public function listForShop(Request $request): JsonResponse
    {
        $paginator = $this->repository->listForShop($request);
        return InventoryStoreResource::collection($paginator)->response();
    }

    public function create(int $shopId, ?int $userId, array $data): JsonResponse
    {
        $data['shop_id'] = $shopId;
        $data['created_by'] = $userId;
        $row = $this->repository->create($data);
        return (new InventoryStoreResource($row))->response()->setStatusCode(201);
    }
}