<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Http\Resources\SaleResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Traits\StockOutNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleService
{
    use StockOutNotifier;

    public function __construct(
        protected SaleRepositoryInterface $saleRepository,
        protected StockRepositoryInterface $stockRepository,
        protected UserResolutionService $userResolutionService,
        protected OrderNumberService $orderNumberService,
        protected OrderItemService $orderItemService,
    ) {}

    public function list(Request $request): JsonResponse
    {
        return response()->json(SaleResource::collection($this->saleRepository->list($request)));
    }

    public function create(array $data, ?int $userId = null): JsonResponse
    {
        try {
            $sale = DB::transaction(function () use ($data, $userId) {
                $resolvedUserId = $this->userResolutionService->resolveId(
                    $data['userId'] ?? null,
                    $data['userName'] ?? null
                );

                $convertedOrder = null;
                if (!empty($data['orderId'])) {
                    // The frontend passes the order's primary id here.
                    $convertedOrder = Order::find($data['orderId']);
                }

                $aggregated = $this->orderItemService->aggregate($data['items']);

                $sale = $this->saleRepository->create([
                    'user_id' => $resolvedUserId,
                    'user_name' => $data['userName'] ?? null,
                    'created_by' => $userId,
                    'voucher_no' => $data['voucherNo'] ?? null,
                    'order_id' => $data['orderId'] ?? null,
                    'product_id' => $aggregated['product_ids'] ?: null,
                    'product_name' => $aggregated['product_names'],
                    'quantity_sold' => $aggregated['quantities'],
                    'total_price' => $data['grandTotal'],
                    'price_per_unit' => $aggregated['price_per_unit'],
                    'customer_name' => $data['customerName'] ?? null,
                    'customer_phone' => $data['customerPhone'] ?? null,
                    'customer_location' => $data['customerLocation'] ?? null,
                    'pay_method' => $data['payMethod'] ?? null,
                    'items' => $data['items'],
                    'grand_total' => $data['grandTotal'],
                    'discount' => $data['discount'] ?? 0,
                    'notes' => $data['notes'] ?? null,
                ]);

                $numbers = $this->orderNumberService->generate($sale);
                $this->saleRepository->update($sale, $numbers);

                foreach ($data['items'] as $item) {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['productId'] ?? null,
                        'product_name' => $item['productName'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unitPrice'],
                        'subtotal' => $item['subtotal'],
                        'size' => $item['size'] ?? null,
                        'color' => $item['color'] ?? null,
                        'notes' => $item['notes'] ?? null,
                        'created_by' => $userId,
                    ]);
                }

                if ($convertedOrder === null) {
                    // No server order backs this sale, so its stock was
                    // never checked or deducted -- deduct here so the
                    // deduct-then-restore pairing survives a later delete.
                    $this->deductSaleItems($data['items']);
                } else {
                    // The draft's stock pin simply converts: the order row
                    // is removed and the deduction stands. Cancelled orders
                    // are the exception -- cancelling restored their stock,
                    // so this sale must deduct again.
                    if ($convertedOrder->status === 'cancelled') {
                        $this->deductSaleItems($data['items']);
                    } else {
                        // The cashier may have added or removed items after
                        // the draft was saved, so the cart being sold can
                        // differ from the snapshot that pinned the stock.
                        // Move the pin onto the cart before the delete below
                        // leaves it standing for the sale.
                        $this->reconcileDraftStock($convertedOrder, $data['items']);
                    }
                    $convertedOrder->delete();
                }

                return $sale;
            }, 3);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'product' => $e->productName(),
                'requested' => $e->requested(),
                'available' => $e->available(),
            ], 422);
        }

        return response()->json(new SaleResource($this->saleRepository->getWithItems($sale->id)), 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json(new SaleResource($sale->load('saleItems', 'createdByUser')));
    }

    public function update(array $data, Sale $sale, ?int $userId = null): JsonResponse
    {
        $resolvedUserId = $data['userId'] ?? $sale->user_id;
        if (array_key_exists('userName', $data)) {
            $resolvedUserId = $this->userResolutionService->resolveId($resolvedUserId, $data['userName']) ?? $resolvedUserId;
        }

        $productId = $data['productId'] ?? $sale->product_id;
        if (array_key_exists('productName', $data)) {
            $productId = \App\Models\Product::where('name', $data['productName'])->value('id') ?? $productId;
        }

        $updated = $this->saleRepository->update($sale, [
            'user_id' => $resolvedUserId,
            'user_name' => $data['userName'] ?? $sale->user_name,
            'voucher_no' => $data['voucherNo'] ?? $sale->voucher_no,
            'product_id' => $productId,
            'product_name' => $data['productName'] ?? $sale->product_name,
            'order_id' => $data['orderId'] ?? $sale->order_id,
            'quantity_sold' => $data['quantitySold'] ?? $sale->quantity_sold,
            'total_price' => $data['totalPrice'] ?? $sale->total_price,
            'price_per_unit' => $data['pricePerUnit'] ?? $sale->price_per_unit,
            'customer_name' => $data['customerName'] ?? $sale->customer_name,
            'customer_phone' => $data['customerPhone'] ?? $sale->customer_phone,
            'pay_method' => $data['payMethod'] ?? $sale->pay_method,
            'updated_by' => $userId,
        ]);

        return response()->json(new SaleResource($updated->load('saleItems')));
    }

    public function delete(Sale $sale): JsonResponse
    {
        $restored = DB::transaction(function () use ($sale) {
            $sale->load('saleItems');
            $qtyByProduct = [];
            foreach ($sale->saleItems as $saleItem) {
                if (!empty($saleItem->product_id)) {
                    try {
                        $this->stockRepository->restore(
                            $saleItem->product_id,
                            $saleItem->quantity,
                            $saleItem->size ?? null,
                            $saleItem->color ?? null
                        );
                        $qtyByProduct[$saleItem->product_id] =
                            ($qtyByProduct[$saleItem->product_id] ?? 0) + (int) $saleItem->quantity;
                    } catch (\Exception $e) {
                        // Product may have been deleted; skip restore
                    }
                }
            }

                $this->saleRepository->delete($sale);

                return ['saleId' => $sale->id, 'qtyByProduct' => $qtyByProduct];
            }, 3);

        // Queued after the commit so the push reads the restored stock, not
        // the pre-commit snapshot; one push per restored product.
        foreach ($restored['qtyByProduct'] as $productId => $qty) {
            $product = Product::with('package.category.inventory')->find($productId);
            if ($product) {
                $this->notifyStockRestored($product, (int) $qty, "sale_{$restored['saleId']}_{$productId}");
            }
        }

        return response()->json(null, 204);
    }

    public function deleteItem(Sale $sale, \App\Models\SaleItem $saleItem): JsonResponse
    {
        if ($saleItem->sale_id !== $sale->id) {
            return response()->json(['message' => 'Sale item does not belong to this sale.'], 400);
        }

        $itemId = $saleItem->id;
        $restoredProductId = null;
        $restoredQty = 0;

        DB::transaction(function () use ($sale, $saleItem, &$restoredProductId, &$restoredQty) {
            if (!empty($saleItem->product_id)) {
                try {
                    $this->stockRepository->restore(
                        $saleItem->product_id,
                        $saleItem->quantity,
                        $saleItem->size ?? null,
                        $saleItem->color ?? null
                    );
                    $restoredProductId = (int) $saleItem->product_id;
                    $restoredQty = (int) $saleItem->quantity;
                } catch (\Exception $e) {
                    // Product may have been deleted; skip restore
                }
            }

            $saleItem->delete();

            $sale->load('saleItems');
            $sale->update([
                'total_price' => $sale->saleItems->sum('subtotal'),
                'quantity_sold' => $sale->saleItems->sum('quantity'),
            ]);
        });

        if ($restoredProductId) {
            $product = Product::with('package.category.inventory')->find($restoredProductId);
            if ($product) {
                $this->notifyStockRestored($product, $restoredQty, "item_{$itemId}");
            }
        }

        return response()->json(new \App\Http\Resources\SaleResource($sale->fresh()->load('saleItems')));
    }

    /**
     * Deducts stock for every sale item that carries a product id, inside
     * the caller's transaction. Used when a sale is created without a live
     * draft order that already pinned its stock (no order id, or an order
     * that was cancelled). Ordering matches OrderService so the row locks
     * are acquired in the same global order.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function deductSaleItems(array $items): void
    {
        usort(
            $items,
            static fn (array $a, array $b): int =>
                ((int) ($a['productId'] ?? 0)) <=> ((int) ($b['productId'] ?? 0))
        );

        foreach ($items as $item) {
            if (!empty($item['productId'])) {
                $this->stockRepository->deduct(
                    (int) $item['productId'],
                    (int) $item['quantity'],
                    $item['size'] ?? null,
                    $item['color'] ?? null
                );
            }
        }
    }

    /**
     * Moves a converting draft's stock pin from its saved snapshot onto the
     * cart actually being sold.
     *
     * A draft pins stock for the items it was created with, but the cashier
     * can reopen the card and add or remove lines before converting. Without
     * this step the difference is never deducted (added lines oversell, and
     * deleting the sale later would restore stock that was never taken) or
     * never returned (removed lines leave stock stuck down). Each per-item
     * delta is applied here, inside the sale transaction, so the pin always
     * ends up matching what is actually sold. An unchanged cart produces no
     * deltas, which is the common path and a no-op.
     *
     * @param  array<int, array<string, mixed>>  $newItems
     */
    private function reconcileDraftStock(Order $order, array $newItems): void
    {
        $old = self::quantitiesByStockKey($order->items ?? []);
        $new = self::quantitiesByStockKey($newItems);

        $deltas = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $delta = ($new[$key]['quantity'] ?? 0) - ($old[$key]['quantity'] ?? 0);
            if ($delta === 0) {
                continue;
            }
            $source = $new[$key] ?? $old[$key];
            $deltas[] = [
                'productId' => $source['productId'],
                'size' => $source['size'],
                'color' => $source['color'],
                'delta' => $delta,
            ];
        }

        if ($deltas === []) {
            return;
        }

        // Ascending product id, matching every other stock path so the row
        // locks are acquired in the same global order.
        usort($deltas, static fn (array $a, array $b): int => $a['productId'] <=> $b['productId']);

        foreach ($deltas as $delta) {
            if ($delta['delta'] > 0) {
                $this->stockRepository->deduct($delta['productId'], $delta['delta'], $delta['size'], $delta['color']);
            } else {
                $this->stockRepository->restore($delta['productId'], -$delta['delta'], $delta['size'], $delta['color']);
            }
        }
    }

    /**
     * Sums item quantities keyed by product + size + color, skipping lines
     * with no product id (they were never stock-pinned and never will be).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array{productId: int, size: ?string, color: ?string, quantity: int}>
     */
    private static function quantitiesByStockKey(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            if (empty($item['productId'])) {
                continue;
            }

            $key = self::stockKey($item);
            $map[$key] ??= [
                'productId' => (int) $item['productId'],
                'size' => $item['size'] ?? null,
                'color' => $item['color'] ?? null,
                'quantity' => 0,
            ];
            $map[$key]['quantity'] += (int) ($item['quantity'] ?? 0);
        }

        return $map;
    }

    private static function stockKey(array $item): string
    {
        return sprintf(
            '%d|%s|%s',
            (int) ($item['productId'] ?? 0),
            (string) ($item['size'] ?? ''),
            (string) ($item['color'] ?? '')
        );
    }
}
