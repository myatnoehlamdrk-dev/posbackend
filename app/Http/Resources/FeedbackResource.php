<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'userId' => (string) $this->user_id,
            'shopId' => (string) $this->shop_id,
            'type' => $this->type,
            'message' => $this->message,
            'userName' => $this->user?->name,
            'userEmail' => $this->user?->email,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
