<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->productService->listForShop($request);
    }

    public function search(Request $request): JsonResponse
    {
        return $this->productService->search($request);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        return $this->productService->create($request->validated());
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->notFound('Product not found');
        }

        return $this->productService->show($product);
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->notFound('Product not found');
        }

        return $this->productService->update($request->validated(), $product);
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->notFound('Product not found');
        }

        return $this->productService->delete($product);
    }
}
