<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'userId' => ['nullable', 'integer', 'exists:users,id'],
            'userName' => ['nullable', 'string', 'max:255'],
            'voucherNo' => ['nullable', 'string', 'max:255'],
            'productId' => ['nullable', 'integer', 'exists:products,id'],
            'productName' => ['required', 'string', 'max:255'],
            'orderId' => ['nullable', 'string', 'max:255'],
            'quantitySold' => ['required', 'integer', 'min:1'],
            'totalPrice' => ['required', 'numeric', 'min:0'],
            'pricePerUnit' => ['nullable', 'array'],
            'pricePerUnit.*.name' => ['nullable', 'string'],
            'pricePerUnit.*.price' => ['nullable', 'numeric'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:255'],
            'payMethod' => ['nullable', 'string', 'max:255'],
            'grandTotal' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.productName' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
            'items.*.size' => ['nullable', 'string'],
            'items.*.color' => ['nullable', 'string'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
