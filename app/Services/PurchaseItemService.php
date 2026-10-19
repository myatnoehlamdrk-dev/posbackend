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
        // A token can outlive the shop it was issued for. Without this guard a
        // null shop_id reached the repository's int-typed parameter and the
        // request died as a 500 TypeError instead of an answer the client can
        // act on.
        $shopId = $request->user()?->shop_id;
        if ($shopId === null) {
            return response()->json([
                'message' => 'No shop is associated with this account.',
            ], 403);
        }

        $perPage = $request->integer('per_page', 10);
        $items = $this->purchaseItemRepository->list($shopId, $request->input('status'), $perPage);

        return response()->json(PurchaseItemResource::collection($items));
    }

    public function create(array $data, ?int $userId, ?int $shopId = null): JsonResponse
    {
        // The shop id travels with the row from now on: the list scopes on it
        // directly, so an item can never be written that no session can see.
        $purchaseItem = $this->purchaseItemRepository->create($data, $userId, $userId, $shopId);

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
