<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'customerPhone' => $this->customer_phone ?? '',
            'payMethod' => $this->pay_method ?? '',
            'items' => $this->withItemImages($this->items ?? []),
            'grandTotal' => $this->grand_total ?? 0,
            'discount' => $this->discount ?? 0,
            'notes' => $this->notes ?? '',
            'status' => $this->status ?? 'draft',
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }

    private function withItemImages(array $items): array
    {
        $productIds = [];
        foreach ($items as $item) {
            $id = $item['productId'] ?? null;
            if ($id !== null && $id !== '') {
                $productIds[(string) $id] = true;
            }
        }

        if (empty($productIds)) {
            return $items;
        }

        $images = Product::query()
            ->whereIn('id', array_keys($productIds))
            ->pluck('image', 'id');

        foreach ($items as &$item) {
            $key = (string) ($item['productId'] ?? '');
            $item['imageUrl'] = $images[$key] ?? null;
        }

        return $items;
    }
}
