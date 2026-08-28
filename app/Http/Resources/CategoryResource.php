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
            'type' => $this->inventory?->type ?? '',
            'name' => $this->name,
            'amountOfPackage' => $this->packages_count ?? $this->amount_of_package,
            'packageLimit' => $this->package_limit ?? 0,
            'description' => $this->description ?? '',
            'createdAt' => $this->created_at?->toDateTimeString(),
        ];
    }
}
