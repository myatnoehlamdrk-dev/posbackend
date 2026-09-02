<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'saleId' => (string) $this->sale_id,
            'productId' => $this->product_id ? (string) $this->product_id : null,
            'productName' => $this->product_name ?? '',
            'quantity' => $this->quantity ?? 0,
            'unitPrice' => $this->unit_price ?? 0,
            'subtotal' => $this->subtotal ?? 0,
            'size' => $this->size ?? '',
            'color' => $this->color ?? '',
            'notes' => $this->notes ?? '',
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
