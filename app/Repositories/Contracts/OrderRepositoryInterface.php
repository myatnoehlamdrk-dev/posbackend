<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Support\Collection;

interface OrderRepositoryInterface
{
    public function list(): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function findById(int $id): ?Order;
    public function create(array $data): Order;
    public function update(Order $order, array $data): Order;
    public function delete(Order $order): bool;
    public function findByStatus(string $status): Collection;
}
