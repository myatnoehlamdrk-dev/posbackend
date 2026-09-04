<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['self', 'public'])],
            'name' => ['required', 'string', 'max:255'],
            'packageLimit' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ];
    }
}
