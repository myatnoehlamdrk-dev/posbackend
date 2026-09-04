<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shopId' => ['sometimes', 'required', 'integer', 'exists:shops,id'],
            'type' => ['nullable', 'string'],
            'amountCategory' => ['nullable', 'integer'],
        ];
    }
}
