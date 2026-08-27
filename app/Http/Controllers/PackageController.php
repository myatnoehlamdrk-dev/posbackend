<?php

namespace App\Http\Controllers;

use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(PackageResource::collection(Package::latest()->paginate(20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'amountOfProduct' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'stockStatus' => ['nullable', 'string'],
        ]);

        $package = Package::create([
            'category_id' => $data['categoryId'],
            'name' => $data['name'],
            'amount_of_product' => $data['amountOfProduct'] ?? 0,
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'stock_status' => $data['stockStatus'] ?? null,
        ]);

        return response()->json(new PackageResource($package), 201);
    }

    public function show(Package $package): JsonResponse
    {
        return response()->json(new PackageResource($package));
    }

    public function update(Request $request, Package $package): JsonResponse
    {
        $data = $request->validate([
            'categoryId' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'amountOfProduct' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'stockStatus' => ['nullable', 'string'],
        ]);

        $package->update([
            'category_id' => $data['categoryId'] ?? $package->category_id,
            'name' => $data['name'] ?? $package->name,
            'amount_of_product' => $data['amountOfProduct'] ?? $package->amount_of_product,
            'description' => $data['description'] ?? $package->description,
            'location' => $data['location'] ?? $package->location,
            'stock_status' => $data['stockStatus'] ?? $package->stock_status,
        ]);

        return response()->json(new PackageResource($package));
    }

    public function destroy(Package $package): JsonResponse
    {
        $package->delete();

        return response()->json(['message' => 'Package deleted successfully.']);
    }
}
