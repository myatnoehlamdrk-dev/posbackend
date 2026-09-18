<?php

namespace App\Services;

use App\Http\Resources\PurchaseItemResource;
use App\Models\PurchaseItem;
use App\Repositories\Contracts\PurchaseItemRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseItemService
{
    public function __construct(
        protected PurchaseItemRepositoryInterface $purchaseItemRepository,
    ) {}

    public function list(Request $request): JsonResponse
    {
        $shopId = $request->user()->shop_id;
        $items = $this->purchaseItemRepository->list($shopId, $request->input('status'));

        return response()->json(PurchaseItemResource::collection($items));
    }

    public function create(array $data, ?int $userId): JsonResponse
    {
        $purchaseItem = $this->purchaseItemRepository->create($data, $userId, $userId);

        return response()->json(new PurchaseItemResource($purchaseItem), 201);
    }

    public function show(PurchaseItem $purchaseItem): JsonResponse
    {
        return response()->json(new PurchaseItemResource($purchaseItem->load(['supplier', 'product'])));
    }

    public function update(array $data, PurchaseItem $purchaseItem, ?int $userId = null): JsonResponse
    {
        $data['updated_by'] = $userId;
        $updated = $this->purchaseItemRepository->update($data, $purchaseItem);

        return response()->json(new PurchaseItemResource($updated));
    }

    public function delete(PurchaseItem $purchaseItem): JsonResponse
    {
        $this->purchaseItemRepository->delete($purchaseItem);

        return response()->json(null, 204);
    }
}
