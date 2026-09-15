<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'userId' => $this->user_id ? (string) $this->user_id : null,
            'userName' => $this->user_name,
            'voucherNo' => $this->voucher_no ?? '',
            'orderId' => $this->order_id ?? '',
            'productId' => $this->product_id ?? '',
            'productName' => $this->product_name ?? '',
            'quantitySold' => $this->whenLoaded('saleItems',
                fn () => (string) $this->saleItems->sum('quantity'),
                $this->quantity_sold ?? ''
            ),
            'totalPrice' => $this->whenLoaded('saleItems',
                fn () => $this->saleItems->sum('subtotal'),
                $this->total_price ?? 0
            ),
            'pricePerUnit' => $this->price_per_unit ?? [],
            'customerName' => $this->customer_name ?? '',
            'customerPhone' => $this->customer_phone ?? '',
            'payMethod' => $this->pay_method ?? '',
            'items' => $this->items ?? [],
            'saleItems' => $this->whenLoaded('saleItems', fn ($items) => SaleItemResource::collection($items)->resolve()),
            'grandTotal' => $this->grand_total ?? 0,
            'discount' => $this->discount ?? 0,
            'notes' => $this->notes ?? '',
            'createdBy' => $this->whenLoaded('createdByUser', fn () => $this->createdByUser?->name),
            'updatedBy' => $this->whenLoaded('updatedByUser', fn () => $this->updatedByUser?->name),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
