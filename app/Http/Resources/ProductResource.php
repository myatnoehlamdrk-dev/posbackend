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
            'imageDeleteUrl' => $this->image_delete_url ?? '',
            'stock' => $this->getAvailableStock(),
            'stockAvailable' => $this->getAvailableStock() > 0,
            'size' => $this->size ?? '',
            'brand' => $this->brand ?? '',
            'color' => $this->color ?? '',
            'sku' => $this->sku ?? '',
            'variants' => $this->variants ?? [],
            'category' => $this->whenLoaded('package', function () {
                return optional($this->package)->category?->name ?? '';
            }),
            'categoryId' => $this->whenLoaded('package', function () {
                return optional($this->package)->category_id ? (string) optional($this->package)->category_id : null;
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
            'contractNumber' => $this->whenLoaded('supplier', function () {
                return optional($this->supplier)->contact ?? '';
            }),
            'supplierContact' => $this->supplier_contact ?? '',
            'supplierSince' => $this->whenLoaded('supplier', function () {
                return optional($this->supplier)->created_at ? optional($this->supplier)->created_at->format('Y') : '';
            }),
            'supplierAddress' => $this->supplier_address ?? '',
            'packageId' => $this->package_id ? (string) $this->package_id : null,
            'active' => (bool) $this->active,
        ];
    }
}
