<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->categoryService->listForShop($request);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        return $this->categoryService->create($request->validated(), $request->user()->shop_id);
    }

    public function show(Category $category): JsonResponse
    {
        return $this->categoryService->show($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        return $this->categoryService->update($request->validated(), $category);
    }

    public function destroy(Category $category): JsonResponse
    {
        return $this->categoryService->delete($category);
    }
}
