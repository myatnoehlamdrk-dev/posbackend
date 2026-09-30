<?php

namespace App\Http\Requests;

use App\Models\Feedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in([
                Feedback::TYPE_COMMENT,
                Feedback::TYPE_SUGGESTION,
                Feedback::TYPE_BUG_REPORT,
            ])],
            'message' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
