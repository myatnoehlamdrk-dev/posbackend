<?php

namespace App\Services;

use App\Http\Resources\CategoryResource;
use App\Models\Inventory;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryService
{
    public function __construct(
        protected CategoryRepositoryInterface $categoryRepository,
        protected InventoryService $inventoryService,
    ) {}

    public function listForShop(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'inventoryId' => ['sometimes', 'nullable', 'integer'],
            'type' => ['sometimes', 'nullable', Rule::in(['self', 'public'])],
        ]);

        return response()->json(CategoryResource::collection($this->categoryRepository->listForShop($request)));
    }

    public function create(array $data, int $shopId): JsonResponse
    {
        $inventory = $this->inventoryService->findOrCreateForShop($shopId, $data['type']);

        $category = $this->categoryRepository->create([
            'inventory_id' => $inventory->id,
            'name' => $data['name'],
            'amount_of_package' => 0,
            'package_limit' => $data['packageLimit'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);

        $inventory->increment('amount_category');

        return response()->json(new CategoryResource($category), 201);
    }

    public function show(\App\Models\Category $category): JsonResponse
    {
        $category->load('inventory');
        $category->loadCount('packages');

        return response()->json(new CategoryResource($category));
    }

    public function update(array $data, \App\Models\Category $category): JsonResponse
    {
        $updated = $this->categoryRepository->update($category, [
            'inventory_id' => $data['inventoryId'] ?? $category->inventory_id,
            'name' => $data['name'] ?? $category->name,
            'package_limit' => $data['packageLimit'] ?? $category->package_limit,
            'description' => $data['description'] ?? $category->description,
        ]);

        return response()->json(new CategoryResource($updated));
    }

    public function delete(\App\Models\Category $category): JsonResponse
    {
        $this->categoryRepository->delete($category);

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
