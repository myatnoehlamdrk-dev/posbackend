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
            'quantitySold' => $this->quantity_sold ?? '',
            'totalPrice' => $this->total_price ?? 0,
            'pricePerUnit' => $this->price_per_unit ?? [],
            'customerName' => $this->customer_name ?? '',
            'payMethod' => $this->pay_method ?? '',
            'items' => $this->items ?? [],
            'grandTotal' => $this->grand_total ?? 0,
            'discount' => $this->discount ?? 0,
            'notes' => $this->notes ?? '',
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
