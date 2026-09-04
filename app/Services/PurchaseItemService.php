<?php

namespace App\Services;

use App\Http\Resources\PurchaseItemResource;
use App\Models\PurchaseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseItemService
{
    public function list(Request $request): JsonResponse
    {
        $query = PurchaseItem::with('supplier');

        if ($request->input('status') === 'pending') {
            $query->where('status', 'pending');
        }

        return response()->json(
            PurchaseItemResource::collection($query->latest()->paginate(20))
        );
    }

    public function create(array $data, ?int $userId): JsonResponse
    {
        $totalPrice = $data['quantity'] * $data['unitPrice'];

        $purchaseItem = PurchaseItem::create([
            'user_id' => $userId,
            'supplier_id' => $data['supplierId'] ?? null,
            'product_id' => null,
            'product_name' => $data['productName'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unitPrice'],
            'total_price' => $totalPrice,
            'date' => $data['date'],
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(new PurchaseItemResource($purchaseItem->fresh(['supplier'])), 201);
    }

    public function show(PurchaseItem $purchaseItem): JsonResponse
    {
        return response()->json(new PurchaseItemResource($purchaseItem->load(['supplier', 'product'])));
    }

    public function update(array $data, PurchaseItem $purchaseItem): JsonResponse
    {
        $purchaseItem->update($data);

        return response()->json(new PurchaseItemResource($purchaseItem->fresh(['supplier'])));
    }

    public function delete(PurchaseItem $purchaseItem): JsonResponse
    {
        $purchaseItem->delete();

        return response()->json(['message' => 'Purchase item deleted successfully.']);
    }
}
