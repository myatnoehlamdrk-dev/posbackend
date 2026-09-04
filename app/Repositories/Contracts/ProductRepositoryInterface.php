<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    public function listForShop(Request $request): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function search(string $query): Collection;
    public function findById(int $id): ?Product;
    public function findByName(string $name): ?Product;
    public function create(array $data): Product;
    public function update(Product $product, array $data): Product;
    public function delete(Product $product): bool;
    public function incrementStock(Product $product, int $quantity): void;
}
