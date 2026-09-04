<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected StockRepositoryInterface $stockRepository,
        protected UserResolutionService $userResolutionService,
        protected OrderNumberService $orderNumberService,
        protected OrderItemService $orderItemService,
    ) {}

    public function list(): JsonResponse
    {
        return response()->json(OrderResource::collection($this->orderRepository->list()));
    }

    public function create(array $data): JsonResponse
    {
        try {
            $order = DB::transaction(function () use ($data) {
                $userId = $this->userResolutionService->resolveId(
                    $data['userId'] ?? null,
                    $data['userName'] ?? null
                );

                $aggregated = $this->orderItemService->aggregate($data['items']);

                $order = $this->orderRepository->create([
                    'user_id' => $userId,
                    'user_name' => $data['userName'],
                    'voucher_no' => $data['voucherNo'],
                    'order_id' => $data['orderId'],
                    'product_id' => $aggregated['product_ids'] ?: null,
                    'product_name' => $aggregated['product_names'],
                    'quantity_sold' => $aggregated['quantities'],
                    'total_price' => $data['grandTotal'],
                    'price_per_unit' => $aggregated['price_per_unit'],
                    'customer_name' => $data['customerName'] ?? null,
                    'customer_phone' => $data['customerPhone'] ?? null,
                    'pay_method' => $data['payMethod'] ?? null,
                    'items' => $data['items'],
                    'grand_total' => $data['grandTotal'],
                    'discount' => $data['discount'] ?? 0,
                    'notes' => $data['notes'] ?? null,
                    'status' => $data['status'] ?? 'draft',
                ]);

                $numbers = $this->orderNumberService->generate($order);
                $this->orderRepository->update($order, $numbers);

                foreach ($data['items'] as $item) {
                    if (!empty($item['productId'])) {
                        $this->stockRepository->deduct($item['productId'], $item['quantity']);
                    }
                }

                return $order;
            });
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'product' => $e->productName(),
                'requested' => $e->requested(),
                'available' => $e->available(),
            ], 422);
        }

        return response()->json(new OrderResource($this->orderRepository->findById($order->id)), 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(new OrderResource($order));
    }

    public function update(array $data, Order $order): JsonResponse
    {
        $oldStatus = $order->status;
        $newStatus = $data['status'] ?? $oldStatus;

        DB::transaction(function () use ($order, $data, $oldStatus, $newStatus) {
            $this->orderRepository->update($order, $data);

            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                foreach ($order->items as $item) {
                    if (!empty($item['productId'])) {
                        $this->stockRepository->restore($item['productId'], $item['quantity']);
                    }
                }
            }
        });

        return response()->json(new OrderResource($this->orderRepository->findById($order->id)));
    }

    public function delete(Order $order): JsonResponse
    {
        DB::transaction(function () use ($order) {
            if ($order->status !== 'cancelled') {
                foreach ($order->items as $item) {
                    if (!empty($item['productId'])) {
                        $this->stockRepository->restore($item['productId'], $item['quantity']);
                    }
                }
            }

            $this->orderRepository->delete($order);
        });

        return response()->json(['message' => 'Order deleted successfully.']);
    }
}
