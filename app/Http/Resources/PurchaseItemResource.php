<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'userId' => $this->user_id ? (string) $this->user_id : null,
            'supplierId' => $this->supplier_id ? (string) $this->supplier_id : null,
            'supplierName' => $this->supplier->name ?? '',
            'productId' => $this->product_id ? (string) $this->product_id : null,
            'productName' => $this->product_name ?? '',
            'quantity' => $this->quantity ?? 0,
            'unitPrice' => $this->unit_price ?? 0,
            'totalPrice' => $this->total_price ?? 0,
            'date' => $this->date?->toDateString() ?? '',
            'status' => $this->status ?? 'pending',
            'notes' => $this->notes ?? '',
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
