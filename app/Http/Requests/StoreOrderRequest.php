<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            (new StoreSaleRequest)->rules(),
            ['status' => ['nullable', 'string', 'in:draft,finished']],
        );
    }
}
