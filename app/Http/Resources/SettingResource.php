<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'userId' => (string) $this->user_id,
            'themeMode' => $this->theme_mode,
            'language' => $this->language,
            'shopType' => $this->shop_type,
            'shopImage' => $this->shop_image ?? '',
        ];
    }
}
