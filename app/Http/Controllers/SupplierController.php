<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->supplierService->list();
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        return $this->supplierService->create($request->validated());
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->supplierService->show($supplier);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        return $this->supplierService->update($request->validated(), $supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        return $this->supplierService->delete($supplier);
    }
}
