<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryWithProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        $products = [];
        if ($this->relationLoaded('displayProducts')) {
            $products = ProductResource::collection($this->displayProducts);
        }

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'inventoryId' => (string) $this->inventory_id,
            'type' => $this->inventory?->type ?? '',
            'active' => (bool) $this->active,
            'amountOfPackage' => $this->packages_count ?? $this->amount_of_package,
            'products' => $products,
            'totalProducts' => $this->packages_count ?? 0,
        ];
    }
}
