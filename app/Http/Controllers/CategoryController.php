<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'inventoryId' => ['sometimes', 'nullable', 'integer'],
            'type' => ['sometimes', 'nullable', Rule::in(['self', 'public'])],
        ]);

        $categories = Category::query();

        if ($request->filled('inventoryId')) {
            $inventory = Inventory::where('shop_id', $user->shop_id)
                ->findOrFail($request->integer('inventoryId'));

            $categories->where('inventory_id', $inventory->id);
        } else {
            $categories->whereHas('inventory', function ($query) use ($user, $request) {
                $query->where('shop_id', $user->shop_id);

                if ($request->filled('type')) {
                    $query->where('type', $request->input('type'));
                }
            });
        }

        return response()->json(CategoryResource::collection($categories->withCount('packages')->with('inventory')->latest()->paginate(20)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'type' => ['required', Rule::in(['self', 'public'])],
            'name' => ['required', 'string', 'max:255'],
            'packageLimit' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ]);

        $inventory = Inventory::firstOrCreate(
            ['shop_id' => $user->shop_id, 'type' => $data['type']],
            ['amount_category' => 25]
        );

        $category = Category::create([
            'inventory_id' => $inventory->id,
            'name' => $data['name'],
            'amount_of_package' => 0,
            'package_limit' => $data['packageLimit'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);

        $inventory->increment('amount_category');

        return response()->json(new CategoryResource($category), 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('inventory');
        $category->loadCount('packages');

        return response()->json(new CategoryResource($category));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'inventoryId' => ['sometimes', 'required', 'integer', 'exists:inventories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'packageLimit' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ]);

        $category->update([
            'inventory_id' => $data['inventoryId'] ?? $category->inventory_id,
            'name' => $data['name'] ?? $category->name,
            'package_limit' => $data['packageLimit'] ?? $category->package_limit,
            'description' => $data['description'] ?? $category->description,
        ]);

        return response()->json(new CategoryResource($category));
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
