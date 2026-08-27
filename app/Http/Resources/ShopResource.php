<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShopResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'logoUrl' => $this->shop_image,
            'name' => $this->shop_name,
            'type' => $this->shop_type ?? '',
            'physicalAddress' => $this->shop_physical_address ?? '',
            'ownerInformation' => [
                'name' => $this->owner_name ?? '',
                'email' => $this->owner_email ?? '',
                'phone' => $this->owner_phone ?? '',
            ],
        ];
    }
}
