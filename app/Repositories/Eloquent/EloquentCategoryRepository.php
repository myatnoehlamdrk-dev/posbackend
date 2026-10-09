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

        $query = $this->model->query()->where('active', true);

        if ($request->filled('inventoryId')) {
            $inventory = \App\Models\Inventory::where('shop_id', $user->shop_id)
                ->findOrFail($request->integer('inventoryId'));
            $query->where('inventory_id', $inventory->id);

            if ($inventory->type === 'self') {
                $query->where('user_id', $user->id);
            }
        } else {
            $query->whereHas('inventory', function ($q) use ($user, $request) {
                $q->where('shop_id', $user->shop_id);
                if ($request->filled('type')) {
                    $q->where('type', $request->input('type'));
                }
            });

            $query->where(function ($q) use ($user) {
                $q->whereHas('inventory', fn ($iq) => $iq->where('type', 'public'))
                  ->orWhere('user_id', $user->id);
            });
        }

        // `per_page` is clamped to 100 by the service, so this cannot be used
        // to pull an unbounded page; the default stays 20 for clients that do
        // not ask. `page` is read from the request by the paginator itself.
        return $query->withCount('packages')->with('inventory', 'packages.products', 'createdByUser', 'updatedByUser')->latest()->paginate($request->integer('per_page', 20));
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
        return $category->update(['active' => false]);
    }

    public function countProducts(Category $category): int
    {
        return $category->packages()->sum('amount_of_product');
    }

    public function listForShopWithProducts(Request $request, int $productLimit = 4): \Illuminate\Support\Collection
    {
        $user = $request->user();

        $query = $this->model->query()->where('active', true);

        if ($request->filled('inventoryId')) {
            $inventory = \App\Models\Inventory::where('shop_id', $user->shop_id)
                ->findOrFail($request->integer('inventoryId'));
            $query->where('inventory_id', $inventory->id);

            if ($inventory->type === 'self') {
                $query->where('user_id', $user->id);
            }
        } else {
            $query->whereHas('inventory', function ($q) use ($user, $request) {
                $q->where('shop_id', $user->shop_id);
                if ($request->filled('type')) {
                    $q->where('type', $request->input('type'));
                }
            });

            $query->where(function ($q) use ($user) {
                $q->whereHas('inventory', fn ($iq) => $iq->where('type', 'public'))
                  ->orWhere('user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->withCount('packages')
            ->withCount(['products as products_count' => fn ($q) => $q->where('products.active', true)])
            ->with('inventory', 'createdByUser', 'updatedByUser')
            ->orderBy('name', 'asc')
            ->get();

        $this->attachDisplayProducts($categories, $productLimit);

        return $categories->filter(fn ($cat) => $cat->displayProducts->isNotEmpty())->values();
    }

    /**
     * Load the product preview for every category in two queries instead of
     * one query per category: with the API on TiDB Cloud each round trip is a
     * network hop, so N+1 previews dominate the endpoint's latency.
     *
     * Only `products.id` and `packages.category_id` are read for the whole
     * set (the heavy columns stay out of it), the per-category slice is taken
     * in PHP, and the relations are eager-loaded just for that slice.
     *
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     */
    protected function attachDisplayProducts($categories, int $productLimit): void
    {
        $categoryIds = $categories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return;
        }

        $matches = \App\Models\Product::query()
            ->join('packages', 'packages.id', '=', 'products.package_id')
            ->whereIn('packages.category_id', $categoryIds)
            ->where('products.active', true)
            ->orderBy('products.id')
            ->get(['products.id', 'packages.category_id']);

        $idsByCategory = $matches
            ->groupBy('category_id')
            ->map(fn ($rows) => $rows->take($productLimit)->pluck('id')->all());

        $selectedIds = collect($idsByCategory)->flatten()->values()->all();

        $products = $selectedIds === []
            ? collect()
            : \App\Models\Product::with('package.category.inventory', 'supplier')
                ->whereIn('id', $selectedIds)
                ->orderBy('id')
                ->get()
                ->keyBy('id');

        foreach ($categories as $category) {
            $category->setRelation(
                'displayProducts',
                collect($idsByCategory[$category->id] ?? [])
                    ->map(fn ($id) => $products[$id])
                    ->values()
            );
        }
    }
}
