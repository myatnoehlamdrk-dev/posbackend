<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'shopId' => (string) $this->shop_id,
            'type' => $this->type ?? '',
            'amountCategory' => $this->amount_category,
        ];
    }
}
