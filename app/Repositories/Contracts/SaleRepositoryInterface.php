<?php

namespace App\Repositories\Contracts;

use App\Models\Sale;
use Illuminate\Support\Collection;

interface SaleRepositoryInterface
{
    public function list(): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function findById(int $id): ?Sale;
    public function create(array $data): Sale;
    public function update(Sale $sale, array $data): Sale;
    public function delete(Sale $sale): bool;
    public function getWithItems(int $id): ?Sale;
}
