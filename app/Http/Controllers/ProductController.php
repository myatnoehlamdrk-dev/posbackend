<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ImgBBService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ImgBBService $imgbb
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Product::query()
            ->where('active', true)
            ->whereHas('package.category.inventory', function ($q) use ($user) {
                $q->where('shop_id', $user->shop_id);
            })
            ->with('package.category.inventory', 'supplier');

        if ($request->filled('packageId')) {
            $query->where('package_id', $request->integer('packageId'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return response()->json(ProductResource::collection($query->latest()->paginate(20)));
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->input('q', '');
        $user = $request->user();

        $products = Product::query()
            ->where('active', true)
            ->where('name', 'like', "%{$search}%")
            ->with('supplier')
            ->limit(20)
            ->get();

        return response()->json(ProductResource::collection($products));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'isSet' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'string'],
            'imageDeleteUrl' => ['nullable', 'string'],
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
            'supplierContact' => ['nullable', 'string', 'max:255'],
            'supplierSince' => ['nullable', 'string', 'max:255'],
            'supplierAddress' => ['nullable', 'string', 'max:255'],
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

        $existingProduct = Product::where('name', $data['name'])->first();

        if ($existingProduct) {
            $existingProduct->increment('stock', $stock ?? 0);

            if (!empty($supplierId)) {
                $existingProduct->update([
                    'supplier_id' => $supplierId,
                    'supplier_contact' => $data['supplierContact'] ?? $existingProduct->supplier_contact,
                    'supplier_since' => $data['supplierSince'] ?? $existingProduct->supplier_since,
                    'supplier_address' => $data['supplierAddress'] ?? $existingProduct->supplier_address,
                ]);
            }

            if (!empty($data['size'])) {
                $existingProduct->update(['size' => $data['size']]);
            }
            if (!empty($data['color'])) {
                $existingProduct->update(['color' => $data['color']]);
            }
            if (!empty($data['brand'])) {
                $existingProduct->update(['brand' => $data['brand']]);
            }
            if (!empty($data['sku'])) {
                $existingProduct->update(['sku' => $data['sku']]);
            }

            if ($variants !== null) {
                $existingVariants = $existingProduct->variants ?? [];
                $mergedVariants = array_merge($existingVariants, $variants);
                $existingProduct->update(['variants' => $mergedVariants]);
            }

            return response()->json(new ProductResource($existingProduct));
        }

        $product = Product::create([
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
            'imageDeleteUrl' => ['nullable', 'string'],
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
            'supplierContact' => ['nullable', 'string', 'max:255'],
            'supplierSince' => ['nullable', 'string', 'max:255'],
            'supplierAddress' => ['nullable', 'string', 'max:255'],
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

        $newImage = $data['image'] ?? $product->image;
        $newDeleteUrl = $data['imageDeleteUrl'] ?? $product->image_delete_url;

        if (!empty($data['image']) && $data['image'] !== $product->image && !empty($product->image_delete_url)) {
            try {
                $this->imgbb->delete($product->image_delete_url, $product->image_delete_hash);
            } catch (\Throwable $e) {
                // Non-fatal: the update must still proceed even if the old
                // image cannot be removed from ImgBB.
                report($e);
            }
        }

        $product->update([
            'is_set' => $data['isSet'] ?? $product->is_set,
            'name' => $data['name'] ?? $product->name,
            'image' => $newImage,
            'image_delete_url' => $newDeleteUrl,
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

        return response()->json(new ProductResource($product));
    }

    public function destroy(Product $product): JsonResponse
    {
        if (!empty($product->image_delete_url)) {
            $this->imgbb->delete($product->image_delete_url);
        }

        $product->update(['active' => false]);

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
