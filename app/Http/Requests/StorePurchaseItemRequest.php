<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'productName' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unitPrice' => ['required', 'integer', 'min:0'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'size' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'brand' => ['nullable', 'string'],
            'sku' => ['nullable', 'string'],
        ];
    }
}
