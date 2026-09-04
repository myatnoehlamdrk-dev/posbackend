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
            ->whereHas('package.category.inventory', function ($q) use ($user) {
                $q->where('shop_id', $user->shop_id);
            })
            ->with('package.category.inventory', 'supplier');

        if ($request->filled('packageId')) {
            $query->where('package_id', $request->integer('packageId'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate(20);
    }

    public function search(string $query): Collection
    {
        return $this->model->query()
            ->where('active', true)
            ->where('name', 'like', "%{$query}%")
            ->with('supplier')
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
