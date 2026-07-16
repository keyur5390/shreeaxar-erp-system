<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('iso_code')) {
            $this->merge(['iso_code' => strtoupper((string) $this->input('iso_code'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('countries', 'name')->ignore($this->route('country')),
            ],
            'iso_code' => [
                'required',
                'string',
                'regex:/^[A-Z]{2,3}$/',
                Rule::unique('countries', 'iso_code')->ignore($this->route('country')),
            ],
        ];
    }
}
