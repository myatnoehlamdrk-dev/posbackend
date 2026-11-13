<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperationFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'in:premises,transaction,fulfilment,people,other'],
            'calculation' => ['nullable', 'string', 'in:fixedPerMonth,percentageOfRevenue,perOrder,oneTime'],
            'rate' => ['required', 'numeric', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
            'icon' => ['nullable', 'string', 'in:premises,transaction,fulfilment,people,other'],
            'tint' => ['nullable', 'string', 'in:blue,violet,orange,green,gray'],
        ];
    }
}
