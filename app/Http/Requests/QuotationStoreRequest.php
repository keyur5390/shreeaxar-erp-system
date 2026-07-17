<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class QuotationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'status_id' => ['required', 'uuid', 'exists:quotation_statuses,id'],
            'quotation_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date'],
            'authorized_by_id' => ['required', 'uuid', 'exists:users,id'],
            'bank_detail_id' => ['nullable', 'uuid', 'exists:bank_details,id'],
            'currency_id' => ['required', 'uuid', 'exists:currencies,id'],
            'terms_conditions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.image_url' => ['nullable', 'string', 'max:500'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.is_tax_included' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('quotation_date') && $this->filled('expiry_date')) {
                if (Carbon::parse($this->input('expiry_date'))->lte(Carbon::parse($this->input('quotation_date')))) {
                    $validator->errors()->add('expiry_date', 'Expiry date must be after quotation date.');
                }
            }
        });
    }
}
