<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categoryId' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'productLimit' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'stockStatus' => ['nullable', 'string'],
        ];
    }
}
