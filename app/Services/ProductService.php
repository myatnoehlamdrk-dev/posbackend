<?php

namespace App\Services;

use App\Http\Resources\ProductResource;
use App\Jobs\DeleteImgbbImageJob;
use App\Models\PurchaseItem;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Traits\StockOutNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductService
{
    use StockOutNotifier;

    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected SupplierRepositoryInterface $supplierRepository,
    ) {}

    public function listForShop(Request $request): JsonResponse
    {
        // Returned through ->response() rather than wrapped in
        // response()->json(): that wrapper serializes the resource collection
        // flat -- items only -- because `meta` is added inside
        // ResourceCollection::toResponse(), which it bypasses. Without the
        // paginator the client had to guess whether another page existed and
        // asked for one guaranteed extra page on every scroll.
        return ProductResource::collection($this->productRepository->listForShop($request))->response();
    }

    public function latest(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $products = $this->productRepository->latestForShop($request, $request->integer('limit', 4));

        return response()->json(ProductResource::collection($products));
    }

    public function search(Request $request): JsonResponse
    {
        $products = $this->productRepository->search($request);
        return response()->json(ProductResource::collection($products));
    }

    public function create(array $data, ?int $userId = null): JsonResponse
    {
        $variants = $data['variants'] ?? null;
        $stock = $data['stock'] ?? null;
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
            'product_type' => (!empty($variants) && count($variants) > 0) ? 'variant' : 'simple',
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
            'created_by' => $userId,
        ]);

        $this->linkPurchaseItem($data['purchaseItemId'] ?? null, $product->id);

        // A stocked product landing in a package ends its "empty" stretch.
        $this->clearRecoveryClaims($product);

        return response()->json(new ProductResource($product), 201);
    }

    public function show(\App\Models\Product $product): JsonResponse
    {
        $product->load('package.category.inventory', 'supplier');
        return response()->json(new ProductResource($product));
    }

    public function update(array $data, \App\Models\Product $product, ?int $userId = null): JsonResponse
    {
        $variants = $data['variants'] ?? null;
        $stock = $data['stock'] ?? null;
        if ($variants !== null && $stock === null) {
            $stock = collect($variants)->sum('quantity');
        }

        $oldStock = $product->stock ?? 0;
        $newStock = $stock ?? $product->stock ?? 0;

        // Resolved before the update: once package_id changes, a lazy
        // $product->package would point at the NEW package instead of the
        // one the product just left (which may now be empty).
        $leftPackage = null;
        if (
            array_key_exists('packageId', $data)
            && (string) ($data['packageId'] ?? '') !== (string) ($product->package_id ?? '')
        ) {
            $leftPackage = $product->package;
        }

        $supplierId = $this->supplierRepository->resolveOrCreate(
            $data['supplierId'] ?? null,
            $data['supplierName'] ?? null
        );

        $removeImage = (bool) ($data['removeImage'] ?? false);
        $updatedVariants = $variants ?? $product->variants;

        // Decided before the write, delivered after it. Retiring the outgoing
        // image used to be an inline ImgBB call made *before* the row was
        // saved: a slow third party held the whole PATCH open for its 30s HTTP
        // timeout — the save the cashier asked for simply never happened — and
        // when the save did fail, the product kept pointing at a photo that
        // was already deleted.
        $retiredImageDeleteUrl = $this->imageDeleteUrlToRetire($data, $product, $removeImage);

        $updated = DB::transaction(function () use ($data, $product, $userId, $newStock, $updatedVariants, $supplierId, $removeImage) {
            return $this->productRepository->update($product, [
                'is_set' => $data['isSet'] ?? $product->is_set ?? false,
                'name' => $data['name'] ?? $product->name,
                'image' => $removeImage ? null : ($data['image'] ?? $product->image),
                'image_delete_url' => $removeImage ? null : ($data['imageDeleteUrl'] ?? $product->image_delete_url),
                'stock' => $newStock,
                'product_type' => $this->resolveProductType($product, $updatedVariants),
                'size' => $data['size'] ?? optional(head($updatedVariants))['size'] ?? $product->size,
                'brand' => $data['brand'] ?? $product->brand,
                'color' => $data['color'] ?? optional(head($updatedVariants))['color'] ?? $product->color,
                'sku' => $data['sku'] ?? $product->sku,
                'variants' => $updatedVariants,
                'supplier_id' => $supplierId ?? $product->supplier_id,
                'supplier_contact' => $data['supplierContact'] ?? $product->supplier_contact,
                'supplier_since' => $data['supplierSince'] ?? $product->supplier_since,
                'supplier_address' => $data['supplierAddress'] ?? $product->supplier_address,
                'package_id' => array_key_exists('packageId', $data) ? $data['packageId'] : $product->package_id,
                'updated_by' => $userId,
            ]);
        });

        if ($retiredImageDeleteUrl !== null) {
            DeleteImgbbImageJob::dispatch($retiredImageDeleteUrl);
        }

        $this->notifyIfStockOut($updated, $oldStock, (int) $updated->stock);

        if ($leftPackage) {
            $this->notifyPackageIfEmpty($leftPackage);
        }

        // Stock raised (or a stocked product joined its package): the
        // product/package are sellable again, so forget their out-claims
        // and let the next real emptying push.
        $this->clearRecoveryClaims($updated);

        return response()->json(new ProductResource($updated));
    }

    public function delete(\App\Models\Product $product): JsonResponse
    {
        if (!empty($product->image_delete_url)) {
            DeleteImgbbImageJob::dispatch($product->image_delete_url);
        }

        $package = $product->package;

        $this->productRepository->delete($product);

        if ($package) {
            $this->notifyPackageIfEmpty($package);
        }

        return response()->json(null, 204);
    }

    private function mergeIntoExisting(\App\Models\Product $product, array $data, ?int $stock, ?int $supplierId, ?array $variants): void
    {
        $this->productRepository->incrementStock($product, $stock ?? 0);
        $this->clearRecoveryClaims($product);

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
            $mergedVariants = array_merge($existingVariants, $variants);
            $this->productRepository->update($product, [
                'variants' => $mergedVariants,
                'product_type' => 'variant',
            ]);
        }

        $this->linkPurchaseItem($data['purchaseItemId'] ?? null, $product->id);
    }

    /**
     * The delete link of the image this save is throwing away: the one being
     * replaced, or the one the user just removed. Null when the current image
     * survives the save.
     */
    private function imageDeleteUrlToRetire(array $data, \App\Models\Product $product, bool $removeImage): ?string
    {
        if ($removeImage) {
            return $product->image_delete_url ?: null;
        }

        $incoming = $data['image'] ?? null;

        return (!empty($incoming) && $incoming !== $product->image)
            ? ($product->image_delete_url ?: null)
            : null;
    }

    /**
     * Only simple/variant products derive their type from their variants.
     *
     * Recomputing it from the submitted variants alone demoted every bundle
     * (whose stock comes from its components, not from the stock column) to
     * `simple` on its first edit — the client always sends `variants: []` for
     * a product that has none, so the check saw "no variants" and overwrote
     * the type.
     */
    private function resolveProductType(\App\Models\Product $product, ?array $variants): string
    {
        if ($product->product_type === 'bundle' || !empty($product->bundle_components)) {
            return 'bundle';
        }

        return !empty($variants) ? 'variant' : 'simple';
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
