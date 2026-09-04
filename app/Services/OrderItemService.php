<?php

namespace App\Services;

use Illuminate\Support\Collection;

class OrderItemService
{
    /**
     * Aggregate an array of sale/order items into comma-delimited strings
     * and a price-per-unit array for storage in legacy columns.
     *
     * @param  array  $items  Validated items array
     * @return array{product_ids: string, product_names: string, quantities: string, price_per_unit: array}
     */
    public function aggregate(array $items): array
    {
        $collection = collect($items);

        return [
            'product_ids' => $collection->pluck('productId')->filter()->implode(','),
            'product_names' => $collection->pluck('productName')->implode(','),
            'quantities' => $collection->pluck('quantity')->implode(','),
            'price_per_unit' => $collection->map(fn ($item) => [
                'name' => $item['productName'],
                'price' => $item['unitPrice'],
            ])->toArray(),
        ];
    }
}
