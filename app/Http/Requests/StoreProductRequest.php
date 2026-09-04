<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'isSet' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'string'],
            'imageDeleteUrl' => ['nullable', 'string'],
            'stock' => ['nullable', 'integer'],
            'size' => ['nullable', 'string'],
            'brand' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'sku' => ['nullable', 'string'],
            'variants' => ['nullable', 'array'],
            'variants.*.size' => ['nullable', 'string'],
            'variants.*.color' => ['nullable', 'string'],
            'variants.*.quantity' => ['nullable', 'integer'],
            'variants.*.price' => ['nullable', 'numeric'],
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplierName' => ['nullable', 'string', 'max:255'],
            'supplierContact' => ['nullable', 'string', 'max:255'],
            'supplierSince' => ['nullable', 'string', 'max:255'],
            'supplierAddress' => ['nullable', 'string', 'max:255'],
            'packageId' => ['nullable', 'integer', 'exists:packages,id'],
            'purchaseItemId' => ['nullable', 'integer', 'exists:purchase_items,id'],
        ];
    }
}
