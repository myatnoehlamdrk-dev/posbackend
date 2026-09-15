<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface CustomerRepositoryInterface
{
    public function listForShop(Request $request): LengthAwarePaginator;
    public function findById(int $id): ?Customer;
    public function create(array $data): Customer;
    public function update(Customer $customer, array $data): Customer;
    public function delete(Customer $customer): bool;
    public function analytics(int $shopId): array;
    public function searchFromSales(int $shopId, string $query): array;
}
