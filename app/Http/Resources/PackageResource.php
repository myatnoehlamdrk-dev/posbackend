<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'categoryId' => (string) $this->category_id,
            'name' => $this->name,
            'amountOfProduct' => $this->amount_of_product,
            'description' => $this->description ?? '',
            'location' => $this->location ?? '',
            'stockStatus' => $this->stock_status ?? '',
        ];
    }
}
