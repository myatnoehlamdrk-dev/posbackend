<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->orderService->list();
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        return $this->orderService->create($request->validated());
    }

    public function show(Order $order): JsonResponse
    {
        return $this->orderService->show($order);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        return $this->orderService->update($request->validated(), $order);
    }

    public function destroy(Order $order): JsonResponse
    {
        return $this->orderService->delete($order);
    }
}
