<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Traits\StockOutNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderService
{
    use StockOutNotifier;

    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected StockRepositoryInterface $stockRepository,
        protected UserResolutionService $userResolutionService,
        protected OrderNumberService $orderNumberService,
        protected OrderItemService $orderItemService,
    ) {}

    public function list(Request $request): JsonResponse
    {
        return response()->json(OrderResource::collection($this->orderRepository->list($request)));
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
                    'user_name' => $data['userName'] ?? null,
                    'voucher_no' => $data['voucherNo'] ?? null,
                    'order_id' => $data['orderId'] ?? null,
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

                foreach (self::byProductId($data['items']) as $item) {
                    if (!empty($item['productId'])) {
                        $product = Product::with('package.category.inventory')->find($item['productId']);
                        $oldStock = $product ? $product->getAvailableStock() : 0;

                        $this->stockRepository->deduct(
                            $item['productId'],
                            $item['quantity'],
                            $item['size'] ?? null,
                            $item['color'] ?? null
                        );

                        if ($product) {
                            $newStock = $product->fresh()->getAvailableStock();
                            $this->notifyIfStockOut($product, $oldStock, $newStock);
                        }
                    }
                }

                return $order;
            }, 3);
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
                foreach (self::byProductId($order->items ?? []) as $item) {
                    if (!empty($item['productId'])) {
                        $this->stockRepository->restore(
                            $item['productId'],
                            $item['quantity'],
                            $item['size'] ?? null,
                            $item['color'] ?? null
                        );
                    }
                }
            }
        }, 3);

        return response()->json(new OrderResource($this->orderRepository->findById($order->id)));
    }

    public function delete(Order $order): JsonResponse
    {
        DB::transaction(function () use ($order) {
            if ($order->status !== 'cancelled') {
                foreach (self::byProductId($order->items ?? []) as $item) {
                    if (!empty($item['productId'])) {
                        $this->stockRepository->restore(
                            $item['productId'],
                            $item['quantity'],
                            $item['size'] ?? null,
                            $item['color'] ?? null
                        );
                    }
                }
            }

            $this->orderRepository->delete($order);
        }, 3);

        return response()->json(null, 204);
    }

    public function addItems(array $data, Order $order): JsonResponse
    {
        try {
            $order = DB::transaction(function () use ($data, $order) {
                foreach (self::byProductId($data['items']) as $item) {
                    if (!empty($item['productId'])) {
                        $product = Product::with('package.category.inventory')->find($item['productId']);
                        $oldStock = $product ? $product->getAvailableStock() : 0;

                        $this->stockRepository->deduct(
                            $item['productId'],
                            $item['quantity'],
                            $item['size'] ?? null,
                            $item['color'] ?? null
                        );

                        if ($product) {
                            $newStock = $product->fresh()->getAvailableStock();
                            $this->notifyIfStockOut($product, $oldStock, $newStock);
                        }
                    }
                }

                $merged = array_merge($order->items ?? [], $data['items']);
                $aggregated = $this->orderItemService->aggregate($merged);
                $grandTotal = collect($merged)->sum('subtotal');

                return $this->orderRepository->update($order, [
                    'product_id' => $aggregated['product_ids'] ?: null,
                    'product_name' => $aggregated['product_names'],
                    'quantity_sold' => $aggregated['quantities'],
                    'total_price' => $grandTotal,
                    'price_per_unit' => $aggregated['price_per_unit'],
                    'items' => $merged,
                    'grand_total' => $grandTotal,
                ]);
            }, 3);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'product' => $e->productName(),
                'requested' => $e->requested(),
                'available' => $e->available(),
            ], 422);
        }

        return response()->json(new OrderResource($this->orderRepository->findById($order->id)), 200);
    }

    /**
     * Items ordered by ascending product id so every stock operation in the
     * transaction acquires row locks in the same global order. Without a
     * consistent order, two concurrent multi-item orders holding each
     * other's first lock deadlock, and the database aborts one of them.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function byProductId(array $items): array
    {
        usort(
            $items,
            static fn (array $a, array $b): int =>
                ((int) ($a['productId'] ?? 0)) <=> ((int) ($b['productId'] ?? 0))
        );

        return $items;
    }
}
