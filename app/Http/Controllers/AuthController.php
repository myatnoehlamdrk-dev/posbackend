<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string'],
            'social' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'nrc' => ['nullable', 'string'],
            'billingWay' => ['nullable', 'max:255'],
            'dob' => ['nullable', 'string'],
            'gender' => ['nullable', 'string'],
            'shopId' => ['nullable', 'exists:shops,id'],
        ]);

        $user = User::create([
            'name' => $data['fullName'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'social' => $data['social'] ?? null,
            'role' => $data['role'] ?? null,
            'address' => $data['address'] ?? null,
            'nrc_no' => $data['nrc'] ?? null,
            'billing_way' => $data['billingWay'] ?? null,
            'date_of_birth' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
            'shop_id' => $data['shopId'] ?? null,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(array_merge(UserResource::make($user)->resolve(request()), [
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(array_merge(UserResource::make($user)->resolve(request()), [
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(UserResource::make($request->user()->load('shop')));
    }
}
