<?php

namespace App\Services;

use App\Http\Resources\CustomerResource;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerService
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
    ) {}

    public function listForShop(Request $request): JsonResponse
    {
        $paginator = $this->customerRepository->listForShop($request);

        return response()->json([
            'data' => CustomerResource::collection($paginator),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function create(array $data, int $shopId): JsonResponse
    {
        $customer = $this->customerRepository->create([
            'shop_id' => $shopId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        return response()->json(new CustomerResource($customer), 201);
    }

    public function show(\App\Models\Customer $customer): JsonResponse
    {
        return response()->json(new CustomerResource($customer));
    }

    public function update(array $data, \App\Models\Customer $customer): JsonResponse
    {
        $updated = $this->customerRepository->update($customer, [
            'name' => $data['name'] ?? $customer->name,
            'phone' => $data['phone'] ?? $customer->phone,
        ]);

        return response()->json(new CustomerResource($updated));
    }

    public function delete(\App\Models\Customer $customer): JsonResponse
    {
        $this->customerRepository->delete($customer);

        return response()->json(null, 204);
    }

    public function analytics(int $shopId): JsonResponse
    {
        return response()->json($this->customerRepository->analytics($shopId));
    }

    public function searchFromSales(Request $request): JsonResponse
    {
        $shopId = $request->user()->shop_id;
        $query = $request->input('query', '');

        return response()->json($this->customerRepository->searchFromSales($shopId, $query));
    }
}
