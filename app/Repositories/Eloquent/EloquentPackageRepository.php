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

        $query = $this->model->query()->where('active', true);

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

        // `with('products')` used to ride along for the resource's three
        // preview image strings, dragging the whole catalog over the wire on
        // every page load (TiDB Cloud — each row is a network hop). The
        // previews come from one small query instead; the counts stay live.
        $packages = $query->withCount('products')
            ->with('createdByUser', 'updatedByUser')
            ->latest()
            ->paginate(20);

        $this->attachPreviewImages($packages->getCollection());

        return $packages;
    }

    /**
     * At most three product image URLs per package, in one query reading only
     * `id`/`image` — the preview the resource used to carve out of a full
     * `products` eager load, without shipping the catalog. Ordered by product
     * id so the preview is stable.
     *
     * @param  \Illuminate\Support\Collection<int, Package>  $packages
     */
    protected function attachPreviewImages(\Illuminate\Support\Collection $packages): void
    {
        $packageIds = $packages->pluck('id');

        if ($packageIds->isEmpty()) {
            return;
        }

        $matches = \App\Models\Product::query()
            ->whereIn('package_id', $packageIds)
            ->where('active', true)
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->get(['id', 'image', 'package_id']);

        $byPackage = $matches->groupBy('package_id');

        foreach ($packages as $package) {
            $package->setRelation(
                'previewProducts',
                collect($byPackage[$package->id] ?? [])->take(3)->values(),
            );
        }
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
        return $package->update(['active' => false]);
    }

    public function countProducts(Package $package): int
    {
        return $package->products()->count();
    }
}
