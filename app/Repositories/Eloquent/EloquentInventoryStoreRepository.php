<?php

namespace App\Repositories\Eloquent;

use App\Models\InventoryStore;
use App\Repositories\Contracts\InventoryStoreRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EloquentInventoryStoreRepository implements InventoryStoreRepositoryInterface
{
    public function create(array $data): \App\Models\InventoryStore
    {
        return \App\Models\InventoryStore::create($data);
    }

    public function update(\App\Models\InventoryStore $row, array $data): \App\Models\InventoryStore
    {
        $row->update($data);
        return $row->fresh();
    }

    public function listForShop(Request $request): LengthAwarePaginator
    {
        $query = InventoryStore::query()->where('shop_id', $request->user()->shop_id)->where('active', true);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        return $query->latest()->paginate($perPage);
    }
}