<?php

namespace App\Http\Requests\Concerns;

trait ValidatesSaleItems
{
    /**
     * Validation rules shared between Sale and Order store requests.
     */
    protected function saleItemRules(): array
    {
        return [
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
        ];
    }
}
