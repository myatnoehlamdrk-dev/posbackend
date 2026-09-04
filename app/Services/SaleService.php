<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Http\Resources\SaleResource;
use App\Models\SaleItem;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        protected SaleRepositoryInterface $saleRepository,
        protected StockRepositoryInterface $stockRepository,
        protected UserResolutionService $userResolutionService,
        protected OrderNumberService $orderNumberService,
        protected OrderItemService $orderItemService,
    ) {}

    public function list(): JsonResponse
    {
        return response()->json(SaleResource::collection($this->saleRepository->list()));
    }

    public function create(array $data): JsonResponse
    {
        try {
            $sale = DB::transaction(function () use ($data) {
                $userId = $this->userResolutionService->resolveId(
                    $data['userId'] ?? null,
                    $data['userName'] ?? null
                );

                $aggregated = $this->orderItemService->aggregate($data['items']);

                $sale = $this->saleRepository->create([
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
                    ]);
                }

                foreach ($data['items'] as $item) {
                    if (!empty($item['productId'])) {
                        $this->stockRepository->deduct($item['productId'], $item['quantity']);
                    }
                }

                return $sale;
            });
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
        return response()->json(new SaleResource($sale));
    }

    public function update(array $data, Sale $sale): JsonResponse
    {
        $userId = $data['userId'] ?? $sale->user_id;
        if (array_key_exists('userName', $data)) {
            $userId = $this->userResolutionService->resolveId($userId, $data['userName']) ?? $userId;
        }

        $productId = $data['productId'] ?? $sale->product_id;
        if (array_key_exists('productName', $data)) {
            $productId = \App\Models\Product::where('name', $data['productName'])->value('id') ?? $productId;
        }

        $updated = $this->saleRepository->update($sale, [
            'user_id' => $userId,
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
        ]);

        return response()->json(new SaleResource($updated));
    }

    public function delete(Sale $sale): JsonResponse
    {
        DB::transaction(function () use ($sale) {
            foreach ($sale->items as $item) {
                if (!empty($item['productId'])) {
                    $this->stockRepository->restore($item['productId'], $item['quantity']);
                }
            }

            $this->saleRepository->delete($sale);
        });

        return response()->json(['message' => 'Sale deleted successfully.']);
    }
}
