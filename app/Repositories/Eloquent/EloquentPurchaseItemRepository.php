<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseItem;
use App\Repositories\Contracts\PurchaseItemRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;

class EloquentPurchaseItemRepository implements PurchaseItemRepositoryInterface
{
    public function list(int $shopId, ?string $status = null, int $perPage = 10): Paginator
    {
        // Scoped by the row's own shop_id, not through the creator's user
        // row: joining through users made items silently invisible whenever
        // that link broke — a null user_id, a deleted user, or a user moved
        // to another shop returned an empty page while the data sat right
        // there in the table.
        $query = PurchaseItem::with('supplier', 'user', 'createdByUser', 'updatedByUser')
            ->where('shop_id', $shopId);

        if ($status === 'pending') {
            $query->where('status', 'pending');
        }

        // simplePaginate, not paginate: the COUNT query this skips re-runs the
        // whereHas subquery over a remote TiDB Serverless cluster, and on a cold
        // plan cache that single count cost ~12s of the request's ~15s. Nothing
        // consumes the total -- the service returns a bare resource collection,
        // so the count was paid for data that was thrown away.
        return $query->latest()->simplePaginate($perPage);
    }

    public function create(array $data, ?int $userId, ?int $createdBy = null, ?int $shopId = null): PurchaseItem
    {
        return PurchaseItem::create([
            'user_id' => $userId,
            'shop_id' => $shopId,
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
