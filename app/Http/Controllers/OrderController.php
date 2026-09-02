<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(OrderResource::collection(Order::latest()->paginate(20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['nullable', 'integer', 'exists:users,id'],
            'userName' => ['required', 'string', 'max:255'],
            'voucherNo' => ['required', 'string', 'max:255'],
            'orderId' => ['required', 'string', 'max:255'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:255'],
            'payMethod' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['nullable', 'integer'],
            'items.*.productName' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
            'items.*.size' => ['nullable', 'string'],
            'items.*.color' => ['nullable', 'string'],
            'items.*.notes' => ['nullable', 'string'],
            'grandTotal' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,finished'],
        ]);

        try {
            $order = DB::transaction(function () use ($data) {
                $userId = $data['userId'] ?? null;
                if (empty($userId) && !empty($data['userName'])) {
                    $userId = User::where('name', $data['userName'])->value('id');
                }

                $productIds = collect($data['items'])->pluck('productId')->filter()->implode(',');
                $productNames = collect($data['items'])->pluck('productName')->implode(',');
                $quantities = collect($data['items'])->pluck('quantity')->implode(',');
                $pricePerUnit = collect($data['items'])->map(fn($item) => [
                    'name' => $item['productName'],
                    'price' => $item['unitPrice'],
                ])->toArray();

                $order = Order::create([
                    'user_id' => $userId,
                    'user_name' => $data['userName'],
                    'voucher_no' => $data['voucherNo'],
                    'order_id' => $data['orderId'],
                    'product_id' => $productIds ?: null,
                    'product_name' => $productNames,
                    'quantity_sold' => $quantities,
                    'total_price' => $data['grandTotal'],
                    'price_per_unit' => $pricePerUnit,
                    'customer_name' => $data['customerName'] ?? null,
                    'customer_phone' => $data['customerPhone'] ?? null,
                    'pay_method' => $data['payMethod'] ?? null,
                    'items' => $data['items'],
                    'grand_total' => $data['grandTotal'],
                    'discount' => $data['discount'] ?? 0,
                    'notes' => $data['notes'] ?? null,
                    'status' => $data['status'] ?? 'draft',
                ]);

                $random5 = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
                $order->update([
                    'voucher_no' => 'INV-'.$order->id.'-'.$random5,
                    'order_id' => 'ORD-'.$order->id.'-'.$random5,
                ]);

                foreach ($data['items'] as $item) {
                    if (!empty($item['productId'])) {
                        $this->stockService->deduct($item['productId'], $item['quantity']);
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

        return response()->json(new OrderResource($order->fresh()), 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(new OrderResource($order));
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:draft,finished,cancelled'],
            'payMethod' => ['nullable', 'string', 'max:255'],
        ]);

        $oldStatus = $order->status;
        $newStatus = $data['status'] ?? $oldStatus;

        DB::transaction(function () use ($order, $data, $oldStatus, $newStatus) {
            $order->update($data);

            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                foreach ($order->items as $item) {
                    if (!empty($item['productId'])) {
                        $this->stockService->restore($item['productId'], $item['quantity']);
                    }
                }
            }
        });

        return response()->json(new OrderResource($order->fresh()));
    }

    public function destroy(Order $order): JsonResponse
    {
        DB::transaction(function () use ($order) {
            if ($order->status !== 'cancelled') {
                foreach ($order->items as $item) {
                    if (!empty($item['productId'])) {
                        $this->stockService->restore($item['productId'], $item['quantity']);
                    }
                }
            }

            $order->delete();
        });

        return response()->json(['message' => 'Order deleted successfully.']);
    }
}
