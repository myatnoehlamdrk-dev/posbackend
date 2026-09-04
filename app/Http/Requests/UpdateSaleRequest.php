<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
            'customerPhone' => ['nullable', 'string', 'max:255'],
            'payMethod' => ['nullable', 'string', 'max:255'],
        ];
    }
}
