<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShopResource;
use App\Models\Shop;
use App\Services\FuzzySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if ($query === '') {
            return response()->json(ShopResource::collection(Shop::latest()->paginate(20)));
        }

        $matches = Shop::latest()->get()
            ->filter(function (Shop $shop) use ($query) {
                return FuzzySearchService::matchesAllTokens($query, [
                    $shop->shop_name,
                    $shop->shop_physical_address,
                    $shop->owner_name,
                ]);
            })
            ->map(fn (Shop $shop) => [
                'shop' => $shop,
                'score' => FuzzySearchService::queryScore($query, [
                    $shop->shop_name,
                    $shop->shop_physical_address,
                    $shop->owner_name,
                ]),
            ])
            ->sortByDesc(fn (array $entry) => [$entry['score'], $entry['shop']->shop_name])
            ->take(20)
            ->map(fn (array $entry) => $entry['shop']);

        return response()->json(ShopResource::collection($matches->values()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logoUrl' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string'],
            'physicalAddress' => ['nullable', 'string'],
            'ownerInformation' => ['nullable', 'array'],
            'ownerInformation.name' => ['nullable', 'string'],
            'ownerInformation.email' => ['nullable', 'email'],
            'ownerInformation.phone' => ['nullable', 'string'],
        ]);

        $shop = Shop::create([
            'shop_image' => $data['logoUrl'] ?? null,
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
        $role = strtolower(trim($request->user()->role ?? ''));
        if ($role !== 'owner') {
            return response()->json(['message' => 'Only the shop owner can update shop data.'], 403);
        }

        $data = $request->validate([
            'logoUrl' => ['nullable', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['nullable', 'string'],
            'physicalAddress' => ['nullable', 'string'],
            'ownerInformation' => ['nullable', 'array'],
            'ownerInformation.name' => ['nullable', 'string'],
            'ownerInformation.email' => ['nullable', 'email'],
            'ownerInformation.phone' => ['nullable', 'string'],
        ]);

        $shop->update([
            'shop_image' => $data['logoUrl'] ?? $shop->shop_image,
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
