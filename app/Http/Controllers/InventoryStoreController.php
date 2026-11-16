<?php

namespace App\Http\Controllers;

use App\Services\InventoryStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryStoreController extends Controller
{
    public function __construct(
        private readonly InventoryStoreService $service,
    ) {}

    public function adjust(\Illuminate\Http\Request $request, \App\Models\InventoryStore $inventoryStore): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:add,remove'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);
        return $this->service->adjust(
            $inventoryStore,
            $validated['type'],
            (int) $validated['quantity'],
            $validated['note'] ?? null,
            $request->user()?->id,
        );
    }

    public function update(\Illuminate\Http\Request $request, \App\Models\InventoryStore $inventoryStore): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'universal_identifier' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'string', 'max:255'],
            'quantity_on_hand' => ['nullable', 'integer', 'min:0'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'expiration_date' => ['nullable', 'date'],
            'best_before_date' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
        ]);
        return $this->service->update($inventoryStore, $validated);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->service->listForShop($request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'universal_identifier' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'string', 'max:255'],
            'quantity_on_hand' => ['nullable', 'integer', 'min:0'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'expiration_date' => ['nullable', 'date'],
            'best_before_date' => ['nullable', 'date'],
            'product_id' => ['nullable', 'integer'],
        ]);

        return $this->service->create(
            $request->user()->shop_id,
            $request->user()->id,
            $validated,
        );
    }
}