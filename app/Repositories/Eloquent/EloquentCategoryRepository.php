<?php

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        protected Category $model,
    ) {}

    public function listForShop(Request $request): LengthAwarePaginator
    {
        $user = $request->user();

        $query = $this->model->query();

        if ($request->filled('inventoryId')) {
            $inventory = \App\Models\Inventory::where('shop_id', $user->shop_id)
                ->findOrFail($request->integer('inventoryId'));
            $query->where('inventory_id', $inventory->id);
        } else {
            $query->whereHas('inventory', function ($q) use ($user, $request) {
                $q->where('shop_id', $user->shop_id);
                if ($request->filled('type')) {
                    $q->where('type', $request->input('type'));
                }
            });
        }

        return $query->withCount('packages')->with('inventory')->latest()->paginate(20);
    }

    public function findById(int $id): ?Category
    {
        return $this->model->find($id);
    }

    public function create(array $data): Category
    {
        return $this->model->create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(Category $category): bool
    {
        return $category->delete();
    }

    public function countProducts(Category $category): int
    {
        return $category->packages()->sum('amount_of_product');
    }
}
