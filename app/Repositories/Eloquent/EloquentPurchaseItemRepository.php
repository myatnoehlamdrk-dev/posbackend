<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseItem;
use App\Repositories\Contracts\PurchaseItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentPurchaseItemRepository implements PurchaseItemRepositoryInterface
{
    public function list(int $shopId, ?string $status = null): LengthAwarePaginator
    {
        $query = PurchaseItem::with('supplier', 'user')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId));

        if ($status === 'pending') {
            $query->where('status', 'pending');
        }

        return $query->latest()->paginate(20);
    }

    public function create(array $data, ?int $userId): PurchaseItem
    {
        return PurchaseItem::create([
            'user_id' => $userId,
            'supplier_id' => $data['supplierId'] ?? null,
            'product_id' => null,
            'product_name' => $data['productName'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unitPrice'],
            'total_price' => $data['quantity'] * $data['unitPrice'],
            'date' => $data['date'],
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ])->fresh(['supplier']);
    }

    public function update(array $data, PurchaseItem $purchaseItem): PurchaseItem
    {
        $purchaseItem->update($data);
        return $purchaseItem->fresh(['supplier']);
    }

    public function delete(PurchaseItem $purchaseItem): bool
    {
        return $purchaseItem->delete();
    }
}
