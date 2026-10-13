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

        $inventory = null;

        if ($request->filled('inventoryId')) {
            $inventory = \App\Models\Inventory::where('shop_id', $user->shop_id)
                ->findOrFail($request->integer('inventoryId'));
        }

        $isPublic = $inventory !== null
            ? $inventory->type === 'public'
            : $request->input('type') === 'public';

        if ($isPublic) {
            // The public inventory is the shop-wide catalogue: it lists every
            // category in the shop rather than only the rows filed under its
            // own inventory_id — once categories started living in per-user
            // "self" inventories, filtering by inventory_id left the public
            // screen empty (the phone only looked populated on stale cache).
            $query->whereHas('inventory', fn ($q) => $q->where('shop_id', $user->shop_id));
        } elseif ($inventory !== null) {
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
        // `packages.products` used to be eager-loaded here so the resource
        // could pick three preview image strings off it — which shipped the
        // entire product catalog across the wire (the database is TiDB Cloud,
        // so every row is a network hop) and made /categories a multi-second
        // endpoint that browsers gave up on mid-request. attachPreviewImages()
        // fetches the same three URLs in one small query instead.
        $categories = $query->withCount('packages')
            ->with('inventory', 'createdByUser', 'updatedByUser')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $this->attachPreviewImages($categories->getCollection());

        return $categories;
    }

    /**
     * At most three product image URLs per category, in one query that reads
     * only `products.id` and `products.image` — the same preview the resource
     * used to carve out of a full `packages.products` eager load, without
     * dragging every column of the catalog over the network. Ordered by
     * product id so the preview is stable between requests.
     *
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     */
    protected function attachPreviewImages(\Illuminate\Support\Collection $categories): void
    {
        $categoryIds = $categories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return;
        }

        $matches = \App\Models\Product::query()
            ->join('packages', 'packages.id', '=', 'products.package_id')
            ->whereIn('packages.category_id', $categoryIds)
            ->where('products.active', true)
            ->whereNotNull('products.image')
            ->where('products.image', '!=', '')
            ->orderBy('products.id')
            ->get(['products.id', 'products.image', 'packages.category_id']);

        $byCategory = $matches->groupBy('category_id');

        foreach ($categories as $category) {
            $category->setRelation(
                'previewProducts',
                collect($byCategory[$category->id] ?? [])->take(3)->values(),
            );
        }
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

        $inventory = null;

        if ($request->filled('inventoryId')) {
            $inventory = \App\Models\Inventory::where('shop_id', $user->shop_id)
                ->findOrFail($request->integer('inventoryId'));
        }

        $isPublic = $inventory !== null
            ? $inventory->type === 'public'
            : $request->input('type') === 'public';

        if ($isPublic) {
            // The public inventory is the shop-wide catalogue: it lists every
            // category in the shop rather than only the rows filed under its
            // own inventory_id — once categories started living in per-user
            // "self" inventories, filtering by inventory_id left the public
            // screen empty (the phone only looked populated on stale cache).
            $query->whereHas('inventory', fn ($q) => $q->where('shop_id', $user->shop_id));
        } elseif ($inventory !== null) {
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
