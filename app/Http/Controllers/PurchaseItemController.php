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
        return $this->purchaseItemService->create($request->validated(), $request->user()?->id);
    }

    public function show(PurchaseItem $purchaseItem): JsonResponse
    {
        return $this->purchaseItemService->show($purchaseItem);
    }

    public function update(UpdatePurchaseItemRequest $request, PurchaseItem $purchaseItem): JsonResponse
    {
        return $this->purchaseItemService->update($request->validated(), $purchaseItem);
    }

    public function destroy(PurchaseItem $purchaseItem): JsonResponse
    {
        return $this->purchaseItemService->delete($purchaseItem);
    }
}
