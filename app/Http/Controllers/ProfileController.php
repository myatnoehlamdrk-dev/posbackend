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

        // `role` used to be accepted here, which was worse than the same
        // mistake in `register`: any authenticated user could PUT their own
        // role to "admin" and then read every route behind the admin
        // middleware. A profile update describes the person, never their
        // permissions -- those move through the admin role endpoint.
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string'],
            'social' => ['nullable', 'string'],
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
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
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

        // A password change is the user's own answer to "I think someone else
        // has my account", so it has to actually log that someone out. Every
        // other session goes; the one making this request survives, otherwise
        // the user would be signed out of the screen they just used to change
        // it.
        $request->user()->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
