<?php

namespace App\Services;

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
        return response()->json($this->customerRepository->listForShop($request));
    }

    public function create(array $data, int $shopId): JsonResponse
    {
        $customer = $this->customerRepository->create([
            'shop_id' => $shopId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_id' => $data['taxId'] ?? null,
        ]);

        return response()->json($customer, 201);
    }

    public function show(\App\Models\Customer $customer): JsonResponse
    {
        return response()->json($customer);
    }

    public function update(array $data, \App\Models\Customer $customer): JsonResponse
    {
        $updated = $this->customerRepository->update($customer, [
            'name' => $data['name'] ?? $customer->name,
            'phone' => $data['phone'] ?? $customer->phone,
            'email' => $data['email'] ?? $customer->email,
            'address' => $data['address'] ?? $customer->address,
            'tax_id' => $data['taxId'] ?? $customer->tax_id,
        ]);

        return response()->json($updated);
    }

    public function delete(\App\Models\Customer $customer): JsonResponse
    {
        $this->customerRepository->delete($customer);

        return response()->json(['message' => 'Customer deleted successfully.']);
    }

    public function incrementStats(int $customerId, int $orderTotal): void
    {
        $this->customerRepository->incrementStats($customerId, $orderTotal);
    }

    public function findOrCreateByName(string $name, int $shopId, ?string $phone = null): \App\Models\Customer
    {
        return $this->customerRepository->findOrCreateByName($name, $shopId, $phone);
    }
}
