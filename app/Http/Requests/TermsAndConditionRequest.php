<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TermsAndConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('terms_and_condition');

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('terms_and_conditions', 'name')->ignore($id),
            ],
            'content' => ['required', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
