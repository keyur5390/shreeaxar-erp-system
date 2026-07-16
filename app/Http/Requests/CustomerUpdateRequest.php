<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'required', 'string', 'max:300'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($this->route('customer')),
            ],
            'secondary_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'secondary_contact' => ['sometimes', 'nullable', 'string', 'max:50'],
            'tin_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('customers', 'tin_number')->ignore($this->route('customer')),
            ],
            'addresses' => ['sometimes', 'array', 'min:1'],
            'addresses.*.address_type_id' => ['required_with:addresses', 'uuid', 'exists:address_types,id'],
            'addresses.*.address_line_1' => ['required_with:addresses', 'string', 'max:500'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:500'],
            'addresses.*.country_id' => ['required_with:addresses', 'uuid', 'exists:countries,id'],
            'addresses.*.state_id' => ['required_with:addresses', 'uuid', 'exists:states,id'],
            'addresses.*.city' => ['nullable', 'string', 'max:150'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
