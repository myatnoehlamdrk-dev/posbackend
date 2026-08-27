<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ProductResource::collection(Product::latest()->paginate(20)));
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
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'packageId' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        $product = Product::create([
            'is_set' => $data['isSet'] ?? false,
            'name' => $data['name'],
            'image' => $data['image'] ?? null,
            'stock' => $data['stock'] ?? 0,
            'size' => $data['size'] ?? null,
            'brand' => $data['brand'] ?? null,
            'color' => $data['color'] ?? null,
            'sku' => $data['sku'] ?? null,
            'supplier_id' => $data['supplierId'] ?? null,
            'package_id' => $data['packageId'] ?? null,
        ]);

        return response()->json(new ProductResource($product), 201);
    }

    public function show(Product $product): JsonResponse
    {
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
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'packageId' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        $product->update([
            'is_set' => $data['isSet'] ?? $product->is_set,
            'name' => $data['name'] ?? $product->name,
            'image' => $data['image'] ?? $product->image,
            'stock' => $data['stock'] ?? $product->stock,
            'size' => $data['size'] ?? $product->size,
            'brand' => $data['brand'] ?? $product->brand,
            'color' => $data['color'] ?? $product->color,
            'sku' => $data['sku'] ?? $product->sku,
            'supplier_id' => $data['supplierId'] ?? $product->supplier_id,
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
