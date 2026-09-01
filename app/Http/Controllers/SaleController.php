<?php

namespace App\Http\Controllers;

use App\Http\Resources\SaleResource;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(SaleResource::collection(Sale::latest()->paginate(20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['nullable', 'integer', 'exists:users,id'],
            'userName' => ['required', 'string', 'max:255'],
            'voucherNo' => ['required', 'string', 'max:255'],
            'orderId' => ['required', 'string', 'max:255'],
            'customerName' => ['nullable', 'string', 'max:255'],
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
        ]);

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

        $sale = Sale::create([
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
            'pay_method' => $data['payMethod'] ?? null,
            'items' => $data['items'],
            'grand_total' => $data['grandTotal'],
            'discount' => $data['discount'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        $random5 = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
        $sale->update([
            'voucher_no' => 'INV-'.$sale->id.'-'.$random5,
            'order_id' => 'ORD-'.$sale->id.'-'.$random5,
        ]);

        return response()->json(new SaleResource($sale->fresh()), 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json(new SaleResource($sale));
    }

    public function update(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['nullable', 'integer', 'exists:users,id'],
            'userName' => ['sometimes', 'required', 'string', 'max:255'],
            'voucherNo' => ['nullable', 'string', 'max:255'],
            'productId' => ['nullable', 'integer', 'exists:products,id'],
            'productName' => ['sometimes', 'required', 'string', 'max:255'],
            'orderId' => ['nullable', 'string', 'max:255'],
            'quantitySold' => ['nullable', 'integer', 'min:1'],
            'totalPrice' => ['nullable', 'numeric', 'min:0'],
            'pricePerUnit' => ['nullable', 'array'],
            'pricePerUnit.*.name' => ['nullable', 'string'],
            'pricePerUnit.*.price' => ['nullable', 'numeric'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'payMethod' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $data['userId'] ?? $sale->user_id;
        if (array_key_exists('userName', $data)) {
            $userId = User::where('name', $data['userName'])->value('id') ?? $userId;
        }

        $productId = $data['productId'] ?? $sale->product_id;
        if (array_key_exists('productName', $data)) {
            $productId = Product::where('name', $data['productName'])->value('id') ?? $productId;
        }

        $sale->update([
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
            'pay_method' => $data['payMethod'] ?? $sale->pay_method,
        ]);

        return response()->json(new SaleResource($sale));
    }

    public function destroy(Sale $sale): JsonResponse
    {
        $sale->delete();

        return response()->json(['message' => 'Sale deleted successfully.']);
    }
}
