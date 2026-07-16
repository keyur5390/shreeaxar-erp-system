<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickCreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:300'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
