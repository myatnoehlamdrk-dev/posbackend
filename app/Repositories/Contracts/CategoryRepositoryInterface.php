<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface CategoryRepositoryInterface
{
    public function listForShop(Request $request): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function findById(int $id): ?Category;
    public function create(array $data): Category;
    public function update(Category $category, array $data): Category;
    public function delete(Category $category): bool;
    public function countProducts(Category $category): int;
}
