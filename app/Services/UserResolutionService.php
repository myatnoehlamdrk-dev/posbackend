<?php

namespace App\Services;

use App\Models\User;

class UserResolutionService
{
    /**
     * Resolve a user ID from either a direct ID or a user name.
     * Returns null if neither is provided or found.
     */
    public function resolveId(?int $userId, ?string $userName): ?int
    {
        if (!empty($userId)) {
            return $userId;
        }

        if (!empty($userName)) {
            $foundId = User::where('name', $userName)->value('id');
            if ($foundId) {
                return (int) $foundId;
            }
        }

        return null;
    }
}
