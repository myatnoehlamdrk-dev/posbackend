<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FcmTokenController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
            'device_id' => ['nullable', 'string'],
        ]);

        $user = Auth::user();

        FcmToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user?->id,
                'shop_id' => $user?->shop_id,
                'platform' => $validated['platform'] ?? null,
                'device_id' => $validated['device_id'] ?? null,
                'active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json(['message' => 'Token registered']);
    }

    public function unregister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        FcmToken::where('token', $validated['token'])->update(['active' => false]);

        return response()->json(['message' => 'Token unregistered']);
    }
}
