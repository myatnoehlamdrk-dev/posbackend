<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryStoreResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'shopId' => $this->shop_id ? (string) $this->shop_id : null,
            'createdBy' => $this->created_by ? (string) $this->created_by : null,
            'productId' => $this->product_id ? (string) $this->product_id : null,
            'sku' => $this->sku,
            'universalIdentifier' => $this->universal_identifier,
            'name' => $this->name,
            'brand' => $this->brand,
            'description' => $this->description,
            'category' => $this->category,
            'locationId' => $this->location_id,
            'quantityOnHand' => (int) $this->quantity_on_hand,
            'quantityAvailable' => (int) $this->quantity_available,
            'safetyStock' => (int) $this->safety_stock,
            'reorderPoint' => (int) $this->reorder_point,
            'costPrice' => (float) $this->cost_price,
            'retailPrice' => (float) $this->retail_price,
            'dimensions' => $this->dimensions,
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'batchNumber' => $this->batch_number,
            'expirationDate' => $this->expiration_date?->toISOString(),
            'bestBeforeDate' => $this->best_before_date?->toISOString(),
            'active' => (bool) $this->active,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}