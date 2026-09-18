<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        $productImages = [];
        if ($this->relationLoaded('packages')) {
            $productImages = $this->packages
                ->flatMap(fn ($pkg) => $pkg->relationLoaded('products') ? $pkg->products : [])
                ->filter(fn ($p) => !empty($p->image) && $p->active == 1)
                ->pluck('image')
                ->take(3)
                ->values()
                ->toArray();
        }

        return [
            'id' => (string) $this->id,
            'active' => (bool) $this->active,
            'inventoryId' => (string) $this->inventory_id,
            'type' => $this->inventory?->type ?? '',
            'name' => $this->name,
            'amountOfPackage' => $this->packages_count ?? $this->amount_of_package,
            'packageLimit' => $this->package_limit ?? 0,
            'description' => $this->description ?? '',
            'createdBy' => $this->whenLoaded('createdByUser', fn () => $this->createdByUser?->name),
            'updatedBy' => $this->whenLoaded('updatedByUser', fn () => $this->updatedByUser?->name),
            'createdAt' => $this->created_at?->toDateTimeString(),
            'updatedAt' => $this->updated_at?->toDateTimeString(),
            'productImages' => $productImages,
        ];
    }
}
