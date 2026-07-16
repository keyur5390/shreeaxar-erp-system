<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:300'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')],
            'secondary_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'secondary_contact' => ['nullable', 'string', 'max:50'],
            'tin_number' => ['nullable', 'string', 'max:100', Rule::unique('customers', 'tin_number')],
            'addresses' => ['required', 'array', 'min:1'],
            'addresses.*.address_type_id' => ['required', 'uuid', 'exists:address_types,id'],
            'addresses.*.address_line_1' => ['required', 'string', 'max:500'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:500'],
            'addresses.*.country_id' => ['required', 'uuid', 'exists:countries,id'],
            'addresses.*.state_id' => ['required', 'uuid', 'exists:states,id'],
            'addresses.*.city' => ['nullable', 'string', 'max:150'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
