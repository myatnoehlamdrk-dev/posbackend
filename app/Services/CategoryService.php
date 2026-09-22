<?php

namespace App\Services;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryWithProductsResource;
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

    public function withProducts(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'inventoryId' => ['sometimes', 'nullable', 'integer'],
            'type' => ['sometimes', 'nullable', Rule::in(['self', 'public'])],
            'productLimit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $productLimit = $request->integer('productLimit', 4);
        $categories = $this->categoryRepository->listForShopWithProducts($request, $productLimit);

        return response()->json(CategoryWithProductsResource::collection($categories));
    }

    public function create(array $data, int $shopId, ?int $userId = null): JsonResponse
    {
        $inventory = $this->inventoryService->findOrCreateForShop($shopId, $data['type']);

        $category = $this->categoryRepository->create([
            'inventory_id' => $inventory->id,
            'user_id' => $userId,
            'name' => $data['name'],
            'amount_of_package' => 0,
            'package_limit' => $data['packageLimit'] ?? 0,
            'description' => $data['description'] ?? null,
            'created_by' => $userId,
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

    public function update(array $data, \App\Models\Category $category, ?int $userId = null): JsonResponse
    {
        $updated = $this->categoryRepository->update($category, [
            'inventory_id' => $data['inventoryId'] ?? $category->inventory_id,
            'name' => $data['name'] ?? $category->name,
            'package_limit' => $data['packageLimit'] ?? $category->package_limit,
            'description' => $data['description'] ?? $category->description,
            'updated_by' => $userId,
        ]);

        return response()->json(new CategoryResource($updated));
    }

    public function delete(\App\Models\Category $category): JsonResponse
    {
        $this->categoryRepository->delete($category);

        return response()->json(null, 204);
    }
}
