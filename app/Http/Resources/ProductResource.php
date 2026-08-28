<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'isSet' => (bool) $this->is_set,
            'name' => $this->name,
            'image' => $this->image ?? '',
            'stock' => $this->stock,
            'size' => $this->size ?? '',
            'brand' => $this->brand ?? '',
            'color' => $this->color ?? '',
            'sku' => $this->sku ?? '',
            'variants' => $this->variants ?? [],
            'category' => $this->whenLoaded('package', function () {
                return optional($this->package)->category?->name ?? '';
            }),
            'packageName' => $this->whenLoaded('package', function () {
                return optional($this->package)->name ?? '';
            }),
            'inventoryType' => $this->whenLoaded('package', function () {
                return optional(optional($this->package)->category)->inventory?->type ?? '';
            }),
            'supplierName' => $this->whenLoaded('supplier', function () {
                return optional($this->supplier)->name ?? '';
            }),
            'supplierId' => $this->supplier_id ? (string) $this->supplier_id : null,
            'packageId' => $this->package_id ? (string) $this->package_id : null,
        ];
    }
}
