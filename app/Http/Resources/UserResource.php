<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'fullName' => $this->name,
            'email' => $this->email,
            'shopId' => (string) ($this->shop_id ?? ''),
            'type' => $this->type,
            'phone' => $this->phone,
            'social' => $this->social,
            'image' => $this->image,
            'imageDeleteUrl' => $this->image_delete_url,
            'role' => $this->role,
            'address' => $this->address,
            'status' => $this->status,
            'nrcNo' => $this->nrc_no,
            'billingWay' => $this->billing_way,
            'dateOfBirth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'activeStatus' => $this->active_status,
            'shop' => new \App\Http\Resources\ShopResource($this->whenLoaded('shop')),
        ];
    }
}
