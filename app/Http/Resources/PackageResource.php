<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray($request): array
    {
        $productImages = [];
        if ($this->relationLoaded('products')) {
            $productImages = $this->products
                ->filter(fn ($p) => !empty($p->image) && $p->active == 1)
                ->pluck('image')
                ->take(3)
                ->values()
                ->toArray();
        }

        return [
            'id' => (string) $this->id,
            'categoryId' => (string) $this->category_id,
            'name' => $this->name,
            'amountOfProduct' => $this->products_count ?? $this->amount_of_product,
            'productLimit' => $this->product_limit ?? 0,
            'description' => $this->description ?? '',
            'location' => $this->location ?? '',
            'stockStatus' => $this->stock_status ?? '',
            'productImages' => $productImages,
        ];
    }
}
