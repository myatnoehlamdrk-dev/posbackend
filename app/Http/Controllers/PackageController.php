<?php

namespace App\Http\Controllers;

use App\Http\Resources\PackageResource;
use App\Models\Category;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'categoryId' => ['sometimes', 'nullable', 'integer'],
        ]);

        $query = Package::query();

        if ($request->filled('categoryId')) {
            $category = Category::whereHas('inventory', function ($q) use ($user) {
                $q->where('shop_id', $user->shop_id);
            })->findOrFail($request->integer('categoryId'));

            $query->where('category_id', $category->id);
        } else {
            $query->whereHas('category.inventory', function ($q) use ($user) {
                $q->where('shop_id', $user->shop_id);
            });
        }

        return response()->json(PackageResource::collection($query->withCount('products')->latest()->paginate(20)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'productLimit' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'stockStatus' => ['nullable', 'string'],
        ]);

        $category = Category::whereHas('inventory', function ($q) use ($user) {
            $q->where('shop_id', $user->shop_id);
        })->findOrFail($data['categoryId']);

        $package = Package::create([
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

    public function show(Package $package): JsonResponse
    {
        $package->loadCount('products');

        return response()->json(new PackageResource($package));
    }

    public function update(Request $request, Package $package): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'categoryId' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'productLimit' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'stockStatus' => ['nullable', 'string'],
        ]);

        if (isset($data['categoryId'])) {
            Category::whereHas('inventory', function ($q) use ($user) {
                $q->where('shop_id', $user->shop_id);
            })->findOrFail($data['categoryId']);
        }

        $package->update([
            'category_id' => $data['categoryId'] ?? $package->category_id,
            'name' => $data['name'] ?? $package->name,
            'product_limit' => $data['productLimit'] ?? $package->product_limit,
            'description' => $data['description'] ?? $package->description,
            'location' => $data['location'] ?? $package->location,
            'stock_status' => $data['stockStatus'] ?? $package->stock_status,
        ]);

        return response()->json(new PackageResource($package));
    }

    public function destroy(Package $package): JsonResponse
    {
        $package->delete();

        return response()->json(['message' => 'Package deleted successfully.']);
    }
}
