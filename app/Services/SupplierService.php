<?php

namespace App\Services;

use App\Http\Resources\SupplierResource;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Http\JsonResponse;

class SupplierService
{
    public function __construct(
        protected SupplierRepositoryInterface $supplierRepository,
    ) {}

    public function list(): JsonResponse
    {
        return response()->json(SupplierResource::collection($this->supplierRepository->list()));
    }

    public function create(array $data): JsonResponse
    {
        $supplier = $this->supplierRepository->create($data);

        return response()->json(new SupplierResource($supplier), 201);
    }

    public function show(\App\Models\Supplier $supplier): JsonResponse
    {
        return response()->json(new SupplierResource($supplier));
    }

    public function update(array $data, \App\Models\Supplier $supplier): JsonResponse
    {
        $updated = $this->supplierRepository->update($supplier, $data);

        return response()->json(new SupplierResource($updated));
    }

    public function delete(\App\Models\Supplier $supplier): JsonResponse
    {
        $this->supplierRepository->delete($supplier);

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }

    public function resolveOrCreate(?int $supplierId, ?string $supplierName): ?int
    {
        return $this->supplierRepository->resolveOrCreate($supplierId, $supplierName);
    }
}
