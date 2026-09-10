<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        protected Order $model,
    ) {}

    public function list(Request $request): LengthAwarePaginator
    {
        $shopId = $request->user()->shop_id;

        return $this->model->with('user')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->latest()
            ->paginate(20);
    }

    public function findById(int $id): ?Order
    {
        return $this->model->find($id);
    }

    public function create(array $data): Order
    {
        return $this->model->create($data);
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);
        return $order->fresh();
    }

    public function delete(Order $order): bool
    {
        return $order->delete();
    }

    public function findByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)->get();
    }
}
