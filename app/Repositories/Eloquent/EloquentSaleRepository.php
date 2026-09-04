<?php

namespace App\Repositories\Eloquent;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentSaleRepository implements SaleRepositoryInterface
{
    public function __construct(
        protected Sale $model,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->model->with('saleItems')->latest()->paginate(20);
    }

    public function findById(int $id): ?Sale
    {
        return $this->model->find($id);
    }

    public function create(array $data): Sale
    {
        return $this->model->create($data);
    }

    public function update(Sale $sale, array $data): Sale
    {
        $sale->update($data);
        return $sale->fresh();
    }

    public function delete(Sale $sale): bool
    {
        return $sale->delete();
    }

    public function getWithItems(int $id): ?Sale
    {
        return $this->model->with('saleItems')->find($id);
    }
}
