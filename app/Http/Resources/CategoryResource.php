<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'inventoryId' => (string) $this->inventory_id,
            'name' => $this->name,
            'amountOfPackage' => $this->amount_of_package,
            'description' => $this->description ?? '',
        ];
    }
}
