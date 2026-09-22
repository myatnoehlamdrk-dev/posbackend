<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseItem;
use App\Repositories\Contracts\PurchaseItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentPurchaseItemRepository implements PurchaseItemRepositoryInterface
{
    public function list(int $shopId, ?string $status = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = PurchaseItem::with('supplier', 'user', 'createdByUser', 'updatedByUser')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId));

        if ($status === 'pending') {
            $query->where('status', 'pending');
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data, ?int $userId, ?int $createdBy = null): PurchaseItem
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
            'created_by' => $createdBy,
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
