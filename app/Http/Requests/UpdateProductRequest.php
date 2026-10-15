<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'isSet' => ['nullable', 'boolean'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // Bounded: the column is VARCHAR(512), and with strict mode a
            // longer value (a stray base64 data-URI) fails the whole save with
            // a SQL 1406 rather than a clean validation error.
            'image' => ['nullable', 'string', 'max:512'],
            'imageDeleteUrl' => ['nullable', 'string'],
            // Explicit removal, distinct from "image omitted" — the service
            // treats a missing/null image as "keep the current one", so
            // clearing a photo had no way to reach the row.
            'removeImage' => ['sometimes', 'boolean'],
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
        ];
    }
}
