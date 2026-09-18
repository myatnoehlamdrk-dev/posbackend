<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
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
        return $this->productService->create($request->validated(), $request->user()->id);
    }

    public function show(Product $product): JsonResponse
    {
        return $this->productService->show($product);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        return $this->productService->update($request->validated(), $product, $request->user()->id);
    }

    public function destroy(Product $product): JsonResponse
    {
        return $this->productService->delete($product);
    }
}
