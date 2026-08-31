<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('shop');

        return response()->json(UserResource::make($user));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string'],
            'social' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'nrc_no' => ['nullable', 'string'],
            'billing_way' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'string'],
            'gender' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'image_delete_url' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        $user->update($data);

        return response()->json(UserResource::make($user->fresh()->load('shop')));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($data['new_password']),
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
