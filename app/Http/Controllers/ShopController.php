<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShopResource;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ShopResource::collection(Shop::latest()->paginate(20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logoData' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string'],
            'physicalAddress' => ['nullable', 'string'],
            'ownerInformation' => ['nullable', 'array'],
            'ownerInformation.name' => ['nullable', 'string'],
            'ownerInformation.email' => ['nullable', 'email'],
            'ownerInformation.phone' => ['nullable', 'string'],
        ]);

        $shop = Shop::create([
            'shop_image' => $data['logoData'] ?? null,
            'shop_name' => $data['name'],
            'shop_type' => $data['type'] ?? null,
            'shop_physical_address' => $data['physicalAddress'] ?? null,
            'owner_name' => $data['ownerInformation']['name'] ?? null,
            'owner_email' => $data['ownerInformation']['email'] ?? null,
            'owner_phone' => $data['ownerInformation']['phone'] ?? null,
        ]);

        return response()->json(new ShopResource($shop), 201);
    }

    public function show(Shop $shop): JsonResponse
    {
        return response()->json(new ShopResource($shop));
    }

    public function update(Request $request, Shop $shop): JsonResponse
    {
        $data = $request->validate([
            'logoData' => ['nullable', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['nullable', 'string'],
            'physicalAddress' => ['nullable', 'string'],
            'ownerInformation' => ['nullable', 'array'],
            'ownerInformation.name' => ['nullable', 'string'],
            'ownerInformation.email' => ['nullable', 'email'],
            'ownerInformation.phone' => ['nullable', 'string'],
        ]);

        $shop->update([
            'shop_image' => $data['logoData'] ?? $shop->shop_image,
            'shop_name' => $data['name'] ?? $shop->shop_name,
            'shop_type' => $data['type'] ?? $shop->shop_type,
            'shop_physical_address' => $data['physicalAddress'] ?? $shop->shop_physical_address,
            'owner_name' => $data['ownerInformation']['name'] ?? $shop->owner_name,
            'owner_email' => $data['ownerInformation']['email'] ?? $shop->owner_email,
            'owner_phone' => $data['ownerInformation']['phone'] ?? $shop->owner_phone,
        ]);

        return response()->json(new ShopResource($shop));
    }

    public function destroy(Shop $shop): JsonResponse
    {
        $shop->delete();

        return response()->json(['message' => 'Shop deleted successfully.']);
    }
}
