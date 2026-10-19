<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseItemRequest;
use App\Http\Requests\UpdatePurchaseItemRequest;
use App\Models\PurchaseItem;
use App\Services\PurchaseItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseItemController extends Controller
{
    public function __construct(
        private readonly PurchaseItemService $purchaseItemService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->purchaseItemService->list($request);
    }

    public function store(StorePurchaseItemRequest $request): JsonResponse
    {
        $user = $request->user();
        // A row written without a shop would be invisible to the very list
        // that scopes on shop_id — the exact way items vanished before.
        // Refuse instead, mirroring the index guard in PurchaseItemService.
        if ($user?->shop_id === null) {
            return response()->json([
                'message' => 'No shop is associated with this account.',
            ], 403);
        }

        return $this->purchaseItemService->create(
            $request->validated(),
            $user?->id,
            $user->shop_id,
        );
    }

    public function show(PurchaseItem $purchaseItem): JsonResponse
    {
        return $this->purchaseItemService->show($purchaseItem);
    }

    public function update(UpdatePurchaseItemRequest $request, PurchaseItem $purchaseItem): JsonResponse
    {
        return $this->purchaseItemService->update($request->validated(), $purchaseItem, $request->user()?->id);
    }

    public function destroy(PurchaseItem $purchaseItem): JsonResponse
    {
        return $this->purchaseItemService->delete($purchaseItem);
    }
}
