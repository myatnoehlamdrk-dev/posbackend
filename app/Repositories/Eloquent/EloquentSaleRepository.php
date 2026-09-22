<?php

namespace App\Repositories\Eloquent;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EloquentSaleRepository implements SaleRepositoryInterface
{
    public function __construct(
        protected Sale $model,
    ) {}

    public function list(Request $request): LengthAwarePaginator
    {
        $shopId = $request->user()->shop_id;

        return $this->model->with('saleItems', 'createdByUser', 'updatedByUser')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->latest()
            ->paginate($request->integer('per_page', 10));
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
        return $this->model->with('saleItems', 'createdByUser', 'updatedByUser')->find($id);
    }
}
