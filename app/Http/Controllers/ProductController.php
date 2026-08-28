<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Product::query()
            ->whereHas('package.category.inventory', function ($q) use ($user) {
                $q->where('shop_id', $user->shop_id);
            })
            ->with('package.category.inventory', 'supplier');

        if ($request->filled('packageId')) {
            $query->where('package_id', $request->integer('packageId'));
        }

        return response()->json(ProductResource::collection($query->latest()->paginate(20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'isSet' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'string'],
            'stock' => ['nullable', 'integer'],
            'size' => ['nullable', 'string'],
            'brand' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'sku' => ['nullable', 'string'],
            'variants' => ['nullable', 'array'],
            'variants.*.size' => ['nullable', 'string'],
            'variants.*.color' => ['nullable', 'string'],
            'variants.*.quantity' => ['nullable', 'integer'],
            'variants.*.price' => ['nullable', 'numeric'],
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplierName' => ['nullable', 'string', 'max:255'],
            'packageId' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        $variants = $data['variants'] ?? null;
        $stock = $data['stock'];
        if ($variants !== null && $stock === null) {
            $stock = collect($variants)->sum('quantity');
        }

        $supplierId = $data['supplierId'] ?? null;
        if (empty($supplierId) && !empty($data['supplierName'])) {
            $supplierId = Supplier::firstOrCreate(['name' => $data['supplierName']])->id;
        }

        $product = Product::create([
            'is_set' => $data['isSet'] ?? false,
            'name' => $data['name'],
            'image' => $data['image'] ?? null,
            'stock' => $stock ?? 0,
            'size' => $data['size'] ?? optional(head($variants))['size'] ?? null,
            'brand' => $data['brand'] ?? null,
            'color' => $data['color'] ?? optional(head($variants))['color'] ?? null,
            'sku' => $data['sku'] ?? null,
            'variants' => $variants,
            'supplier_id' => $supplierId,
            'package_id' => $data['packageId'] ?? null,
        ]);

        return response()->json(new ProductResource($product), 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('package.category.inventory', 'supplier');

        return response()->json(new ProductResource($product));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'isSet' => ['nullable', 'boolean'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'image' => ['nullable', 'string'],
            'stock' => ['nullable', 'integer'],
            'size' => ['nullable', 'string'],
            'brand' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'sku' => ['nullable', 'string'],
            'variants' => ['nullable', 'array'],
            'variants.*.size' => ['nullable', 'string'],
            'variants.*.color' => ['nullable', 'string'],
            'variants.*.quantity' => ['nullable', 'integer'],
            'variants.*.price' => ['nullable', 'numeric'],
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplierName' => ['nullable', 'string', 'max:255'],
            'packageId' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        $variants = $data['variants'] ?? null;
        $stock = $data['stock'];
        if ($variants !== null && $stock === null) {
            $stock = collect($variants)->sum('quantity');
        }

        $supplierId = $data['supplierId'] ?? null;
        if (empty($supplierId) && !empty($data['supplierName'])) {
            $supplierId = Supplier::firstOrCreate(['name' => $data['supplierName']])->id;
        }

        $product->update([
            'is_set' => $data['isSet'] ?? $product->is_set,
            'name' => $data['name'] ?? $product->name,
            'image' => $data['image'] ?? $product->image,
            'stock' => $stock ?? $product->stock,
            'size' => $data['size'] ?? optional(head($variants))['size'] ?? $product->size,
            'brand' => $data['brand'] ?? $product->brand,
            'color' => $data['color'] ?? optional(head($variants))['color'] ?? $product->color,
            'sku' => $data['sku'] ?? $product->sku,
            'variants' => $variants ?? $product->variants,
            'supplier_id' => $supplierId ?? $product->supplier_id,
            'package_id' => $data['packageId'] ?? $product->package_id,
        ]);

        return response()->json(new ProductResource($product));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
