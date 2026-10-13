<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        // The previews are attached by the repository as `previewProducts`
        // (three rows, id + image only). The old `packages.products` eager
        // load existed solely for this array and cost the whole catalog.
        $productImages = $this->relationLoaded('previewProducts')
            ? $this->previewProducts->pluck('image')->values()->toArray()
            : [];

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
