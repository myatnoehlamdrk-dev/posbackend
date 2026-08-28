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
            'productId' => $this->product_id ? (string) $this->product_id : null,
            'productName' => $this->product_name,
            'orderId' => $this->order_id ?? '',
            'quantitySold' => $this->quantity_sold,
            'totalPrice' => $this->total_price,
            'pricePerUnit' => $this->price_per_unit ?? [],
            'customerName' => $this->customer_name ?? '',
            'payMethod' => $this->pay_method ?? '',
        ];
    }
}
