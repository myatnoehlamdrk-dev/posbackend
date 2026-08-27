<?php

namespace App\Http\Controllers;

use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(SupplierResource::collection(Supplier::latest()->paginate(20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ]);

        $supplier = Supplier::create($data);

        return response()->json(new SupplierResource($supplier), 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json(new SupplierResource($supplier));
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ]);

        $supplier->update($data);

        return response()->json(new SupplierResource($supplier));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }
}
