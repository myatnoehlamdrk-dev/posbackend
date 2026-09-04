<?php

namespace App\Services;

use App\Http\Resources\PackageResource;
use App\Models\Category;
use App\Repositories\Contracts\PackageRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageService
{
    public function __construct(
        protected PackageRepositoryInterface $packageRepository,
    ) {}

    public function listForShop(Request $request): JsonResponse
    {
        return response()->json(PackageResource::collection($this->packageRepository->listForShop($request)));
    }

    public function create(array $data, int $shopId): JsonResponse
    {
        $category = Category::whereHas('inventory', function ($q) use ($shopId) {
            $q->where('shop_id', $shopId);
        })->findOrFail($data['categoryId']);

        $package = $this->packageRepository->create([
            'category_id' => $category->id,
            'name' => $data['name'],
            'amount_of_product' => 0,
            'product_limit' => $data['productLimit'] ?? 0,
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'stock_status' => $data['stockStatus'] ?? null,
        ]);

        return response()->json(new PackageResource($package), 201);
    }

    public function show(\App\Models\Package $package): JsonResponse
    {
        $package->loadCount('products');

        return response()->json(new PackageResource($package));
    }

    public function update(array $data, \App\Models\Package $package, int $shopId): JsonResponse
    {
        if (isset($data['categoryId'])) {
            Category::whereHas('inventory', function ($q) use ($shopId) {
                $q->where('shop_id', $shopId);
            })->findOrFail($data['categoryId']);
        }

        $updated = $this->packageRepository->update($package, [
            'category_id' => $data['categoryId'] ?? $package->category_id,
            'name' => $data['name'] ?? $package->name,
            'product_limit' => $data['productLimit'] ?? $package->product_limit,
            'description' => $data['description'] ?? $package->description,
            'location' => $data['location'] ?? $package->location,
            'stock_status' => $data['stockStatus'] ?? $package->stock_status,
        ]);

        return response()->json(new PackageResource($updated));
    }

    public function delete(\App\Models\Package $package): JsonResponse
    {
        $this->packageRepository->delete($package);

        return response()->json(['message' => 'Package deleted successfully.']);
    }
}
