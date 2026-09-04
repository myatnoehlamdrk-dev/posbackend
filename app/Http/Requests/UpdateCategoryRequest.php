<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventoryId' => ['sometimes', 'required', 'integer', 'exists:inventories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'packageLimit' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ];
    }
}
