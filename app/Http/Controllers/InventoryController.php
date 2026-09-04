<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Models\Inventory;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->inventoryService->listForShop($request);
    }

    public function store(StoreInventoryRequest $request): JsonResponse
    {
        return $this->inventoryService->create($request->user()->shop_id, $request->validated()['type']);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return $this->inventoryService->show($inventory);
    }

    public function update(UpdateInventoryRequest $request, Inventory $inventory): JsonResponse
    {
        return $this->inventoryService->update($request->validated(), $inventory);
    }

    public function destroy(Inventory $inventory): JsonResponse
    {
        return $this->inventoryService->delete($inventory);
    }
}
