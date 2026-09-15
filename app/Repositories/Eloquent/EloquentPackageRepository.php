<?php

namespace App\Repositories\Eloquent;

use App\Models\Package;
use App\Repositories\Contracts\PackageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EloquentPackageRepository implements PackageRepositoryInterface
{
    public function __construct(
        protected Package $model,
    ) {}

    public function listForShop(Request $request): LengthAwarePaginator
    {
        $user = $request->user();

        $query = $this->model->query();

        if ($request->filled('categoryId')) {
            $category = \App\Models\Category::whereHas('inventory', function ($q) use ($user) {
                $q->where('shop_id', $user->shop_id);
            })->findOrFail($request->integer('categoryId'));
            $query->where('category_id', $category->id);
        } else {
            $query->whereHas('category.inventory', function ($q) use ($user) {
                $q->where('shop_id', $user->shop_id);
            });
        }

        $query->where(function ($q) use ($user) {
            $q->whereHas('category.inventory', fn ($iq) => $iq->where('type', 'public'))
              ->orWhereHas('category', fn ($cq) => $cq->where('user_id', $user->id));
        });

        return $query->withCount('products')->with('products', 'createdByUser', 'updatedByUser')->latest()->paginate(20);
    }

    public function findById(int $id): ?Package
    {
        return $this->model->find($id);
    }

    public function create(array $data): Package
    {
        return $this->model->create($data);
    }

    public function update(Package $package, array $data): Package
    {
        $package->update($data);
        return $package->fresh();
    }

    public function delete(Package $package): bool
    {
        return $package->delete();
    }

    public function countProducts(Package $package): int
    {
        return $package->products()->count();
    }
}
