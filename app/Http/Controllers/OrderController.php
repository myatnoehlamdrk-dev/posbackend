<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddOrderItemsRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->orderService->list($request);
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

    public function addItems(AddOrderItemsRequest $request, Order $order): JsonResponse
    {
        return $this->orderService->addItems($request->validated(), $order);
    }
}
