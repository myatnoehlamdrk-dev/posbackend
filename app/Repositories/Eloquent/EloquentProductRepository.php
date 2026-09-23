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
        $query = $this->model->query()
            ->where('active', true)
            ->with('package.category.inventory', 'supplier', 'createdByUser', 'updatedByUser');

        $this->applyShopScope($query, $request);

        if ($request->filled('packageId')) {
            $query->where('package_id', $request->integer('packageId'));
        }

        if ($request->filled('categoryId')) {
            $query->whereHas('package', fn ($q) => $q->where('category_id', $request->integer('categoryId')));
        }

        if ($request->filled('search')) {
            $this->applyTokenizedSearch($query, $request->input('search'));
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

    public function search(Request $request): Collection
    {
        $query = $this->model->query()
            ->where('active', true);

        $this->applyShopScope($query, $request);

        $search = trim($request->input('q', ''));
        if ($search !== '') {
            $this->applyTokenizedSearch($query, $search);

            $escaped = addcslashes($search, '%_\\');
            $query->orderByRaw(
                'CASE WHEN name LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END',
                ['%' . $escaped . '%', $escaped . '%']
            );
        }

        return $query
            ->orderBy('name')
            ->with('supplier', 'package.category.inventory')
            ->limit(20)
            ->get();
    }

    private function applyShopScope(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        $user = $request->user();

        $query->where(function ($q) use ($user) {
            $q->whereHas('package.category.inventory', function ($iq) use ($user) {
                $iq->where('shop_id', $user->shop_id);
            })
            ->orWhereNull('package_id');
        });

        $query->where(function ($q) use ($user) {
            $q->whereHas('package.category.inventory', fn ($iq) => $iq->where('type', 'public'))
              ->orWhereHas('package.category', fn ($cq) => $cq->where('user_id', $user->id))
              ->orWhereNull('package_id');
        });
    }

    private function applyTokenizedSearch(\Illuminate\Database\Eloquent\Builder $query, string $search): void
    {
        $tokens = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_slice($tokens, 0, 6);

        foreach ($tokens as $token) {
            $pattern = '%' . addcslashes($token, '%_\\') . '%';
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'like', $pattern)
                  ->orWhere('brand', 'like', $pattern)
                  ->orWhere('sku', 'like', $pattern)
                  ->orWhereHas('package', fn ($pq) => $pq->where('name', 'like', $pattern))
                  ->orWhereHas('package.category', fn ($cq) => $cq->where('name', 'like', $pattern));
            });
        }
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
