<?php

namespace App\Http\Controllers;

use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Inventory::where('shop_id', $user->shop_id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return response()->json(InventoryResource::collection($query->latest()->paginate(20)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'type' => ['required', Rule::in(['self', 'public'])],
        ]);

        $inventory = Inventory::firstOrCreate(
            ['shop_id' => $user->shop_id, 'type' => $data['type']],
            ['amount_category' => 25]
        );

        return response()->json(new InventoryResource($inventory), 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return response()->json(new InventoryResource($inventory));
    }

    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $data = $request->validate([
            'shopId' => ['sometimes', 'required', 'integer', 'exists:shops,id'],
            'type' => ['nullable', 'string'],
            'amountCategory' => ['nullable', 'integer'],
        ]);

        $inventory->update([
            'shop_id' => $data['shopId'] ?? $inventory->shop_id,
            'type' => $data['type'] ?? $inventory->type,
            'amount_category' => $data['amountCategory'] ?? $inventory->amount_category,
        ]);

        return response()->json(new InventoryResource($inventory));
    }

    public function destroy(Inventory $inventory): JsonResponse
    {
        $inventory->delete();

        return response()->json(['message' => 'Inventory deleted successfully.']);
    }
}
