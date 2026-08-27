<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'contact' => $this->contact ?? '',
            'address' => $this->address ?? '',
        ];
    }
}
