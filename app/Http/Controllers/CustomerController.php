<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->customerService->listForShop($request);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        return $this->customerService->create($request->validated(), $request->user()->shop_id);
    }

    public function show(Customer $customer): JsonResponse
    {
        return $this->customerService->show($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        return $this->customerService->update($request->validated(), $customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        return $this->customerService->delete($customer);
    }
}
