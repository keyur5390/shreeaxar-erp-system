<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('quotation_statuses', 'name')->ignore($this->route('quotation_status')),
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/',
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
