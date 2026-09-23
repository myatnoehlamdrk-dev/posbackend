<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        protected Product $model,
    ) {}

    public function listForShop(Request $request): LengthAwarePaginator
    {
        $user = $request->user();

        $query = $this->model->query()
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->whereHas('package.category.inventory', function ($iq) use ($user) {
                    $iq->where('shop_id', $user->shop_id);
                })
                ->orWhereNull('package_id');
            })
            ->with('package.category.inventory', 'supplier', 'createdByUser', 'updatedByUser');

        if ($request->filled('packageId')) {
            $query->where('package_id', $request->integer('packageId'));
        }

        if ($request->filled('categoryId')) {
            $query->whereHas('package', fn ($q) => $q->where('category_id', $request->integer('categoryId')));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('minPrice')) {
            $query->where('stock', '>=', $request->integer('minPrice'));
        }

        if ($request->filled('maxPrice')) {
            $query->where('stock', '<=', $request->integer('maxPrice'));
        }

        if ($request->filled('inStock')) {
            if ($request->input('inStock') === 'true') {
                $query->where('stock', '>', 0);
            } else {
                $query->where('stock', '=', 0);
            }
        }

        $query->where(function ($q) use ($user) {
            $q->whereHas('package.category.inventory', fn ($iq) => $iq->where('type', 'public'))
              ->orWhereHas('package.category', fn ($cq) => $cq->where('user_id', $user->id))
              ->orWhereNull('package_id');
        });

        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $allowedSorts = ['name', 'stock', 'created_at', 'brand'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $order === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        return $query->paginate($request->integer('per_page', 10));
    }

    public function latestForShop(Request $request, int $limit): Collection
    {
        $user = $request->user();

        return $this->model->query()
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->whereHas('package.category.inventory', function ($iq) use ($user) {
                    $iq->where('shop_id', $user->shop_id);
                })
                ->orWhereNull('package_id');
            })
            ->where(function ($q) use ($user) {
                $q->whereHas('package.category.inventory', fn ($iq) => $iq->where('type', 'public'))
                  ->orWhereHas('package.category', fn ($cq) => $cq->where('user_id', $user->id))
                  ->orWhereNull('package_id');
            })
            ->with('package.category.inventory', 'supplier', 'createdByUser', 'updatedByUser')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function search(string $query): Collection
    {
        return $this->model->query()
            ->where('active', true)
            ->where('name', 'like', "%{$query}%")
            ->with('supplier', 'package.category.inventory')
            ->limit(20)
            ->get();
    }

    public function findById(int $id): ?Product
    {
        return $this->model->find($id);
    }

    public function findByName(string $name): ?Product
    {
        return $this->model->where('name', $name)->first();
    }

    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    public function delete(Product $product): bool
    {
        return $product->update(['active' => false]);
    }

    public function incrementStock(Product $product, int $quantity): void
    {
        $product->increment('stock', $quantity);
    }
}
