<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface CustomerRepositoryInterface
{
    public function listForShop(Request $request): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function findById(int $id): ?Customer;
    public function create(array $data): Customer;
    public function update(Customer $customer, array $data): Customer;
    public function delete(Customer $customer): bool;
    public function findOrCreateByName(string $name, int $shopId, ?string $phone = null): Customer;
    public function incrementStats(int $customerId, int $orderTotal): void;
}
