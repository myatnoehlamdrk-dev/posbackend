<?php

namespace App\Services;

use App\Http\Resources\ProductResource;
use App\Models\PurchaseItem;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected SupplierRepositoryInterface $supplierRepository,
        protected ImgBBService $imgbb,
    ) {}

    public function listForShop(Request $request): JsonResponse
    {
        return response()->json(ProductResource::collection($this->productRepository->listForShop($request)));
    }

    public function search(Request $request): JsonResponse
    {
        $products = $this->productRepository->search($request->input('q', ''));
        return response()->json(ProductResource::collection($products));
    }

    public function create(array $data): JsonResponse
    {
        $variants = $data['variants'] ?? null;
        $stock = $data['stock'];
        if ($variants !== null && $stock === null) {
            $stock = collect($variants)->sum('quantity');
        }

        $supplierId = $this->supplierRepository->resolveOrCreate(
            $data['supplierId'] ?? null,
            $data['supplierName'] ?? null
        );

        $existingProduct = $this->productRepository->findByName($data['name']);

        if ($existingProduct) {
            $this->mergeIntoExisting($existingProduct, $data, $stock, $supplierId, $variants);
            return response()->json(new ProductResource($existingProduct));
        }

        $product = $this->productRepository->create([
            'is_set' => $data['isSet'] ?? false,
            'name' => $data['name'],
            'image' => $data['image'] ?? null,
            'image_delete_url' => $data['imageDeleteUrl'] ?? null,
            'stock' => $stock ?? 0,
            'size' => $data['size'] ?? optional(head($variants))['size'] ?? null,
            'brand' => $data['brand'] ?? null,
            'color' => $data['color'] ?? optional(head($variants))['color'] ?? null,
            'sku' => $data['sku'] ?? null,
            'variants' => $variants,
            'supplier_id' => $supplierId,
            'supplier_contact' => $data['supplierContact'] ?? null,
            'supplier_since' => $data['supplierSince'] ?? null,
            'supplier_address' => $data['supplierAddress'] ?? null,
            'package_id' => $data['packageId'] ?? null,
            'active' => true,
        ]);

        $this->linkPurchaseItem($data['purchaseItemId'] ?? null, $product->id);

        return response()->json(new ProductResource($product), 201);
    }

    public function show(\App\Models\Product $product): JsonResponse
    {
        $product->load('package.category.inventory', 'supplier');
        return response()->json(new ProductResource($product));
    }

    public function update(array $data, \App\Models\Product $product): JsonResponse
    {
        $variants = $data['variants'] ?? null;
        $stock = $data['stock'];
        if ($variants !== null && $stock === null) {
            $stock = collect($variants)->sum('quantity');
        }

        $supplierId = $this->supplierRepository->resolveOrCreate(
            $data['supplierId'] ?? null,
            $data['supplierName'] ?? null
        );

        $this->handleImageReplacement($data, $product);

        $updated = $this->productRepository->update($product, [
            'is_set' => $data['isSet'] ?? $product->is_set,
            'name' => $data['name'] ?? $product->name,
            'image' => $data['image'] ?? $product->image,
            'image_delete_url' => $data['imageDeleteUrl'] ?? $product->image_delete_url,
            'stock' => $stock ?? $product->stock,
            'size' => $data['size'] ?? optional(head($variants))['size'] ?? $product->size,
            'brand' => $data['brand'] ?? $product->brand,
            'color' => $data['color'] ?? optional(head($variants))['color'] ?? $product->color,
            'sku' => $data['sku'] ?? $product->sku,
            'variants' => $variants ?? $product->variants,
            'supplier_id' => $supplierId ?? $product->supplier_id,
            'supplier_contact' => $data['supplierContact'] ?? $product->supplier_contact,
            'supplier_since' => $data['supplierSince'] ?? $product->supplier_since,
            'supplier_address' => $data['supplierAddress'] ?? $product->supplier_address,
            'package_id' => $data['packageId'] ?? $product->package_id,
        ]);

        return response()->json(new ProductResource($updated));
    }

    public function delete(\App\Models\Product $product): JsonResponse
    {
        if (!empty($product->image_delete_url)) {
            $this->imgbb->delete($product->image_delete_url);
        }

        $this->productRepository->delete($product);

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    private function mergeIntoExisting(\App\Models\Product $product, array $data, ?int $stock, ?int $supplierId, ?array $variants): void
    {
        $this->productRepository->incrementStock($product, $stock ?? 0);

        if (!empty($supplierId)) {
            $this->productRepository->update($product, [
                'supplier_id' => $supplierId,
                'supplier_contact' => $data['supplierContact'] ?? $product->supplier_contact,
                'supplier_since' => $data['supplierSince'] ?? $product->supplier_since,
                'supplier_address' => $data['supplierAddress'] ?? $product->supplier_address,
            ]);
        }

        foreach (['size', 'color', 'brand', 'sku'] as $field) {
            if (!empty($data[$field])) {
                $this->productRepository->update($product, [$field => $data[$field]]);
            }
        }

        if ($variants !== null) {
            $existingVariants = $product->variants ?? [];
            $this->productRepository->update($product, ['variants' => array_merge($existingVariants, $variants)]);
        }

        $this->linkPurchaseItem($data['purchaseItemId'] ?? null, $product->id);
    }

    private function handleImageReplacement(array $data, \App\Models\Product $product): void
    {
        if (!empty($data['image']) && $data['image'] !== $product->image && !empty($product->image_delete_url)) {
            try {
                $this->imgbb->delete($product->image_delete_url, $product->image_delete_hash);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function linkPurchaseItem(?int $purchaseItemId, int $productId): void
    {
        if (!empty($purchaseItemId)) {
            PurchaseItem::where('id', $purchaseItemId)
                ->whereNull('product_id')
                ->update(['product_id' => $productId, 'status' => 'completed']);
        }
    }
}
