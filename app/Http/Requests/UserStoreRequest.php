<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'role_id' => [
                'required',
                'uuid',
                Rule::exists('roles', 'id')->where('guard_name', 'api'),
            ],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'addresses' => ['required', 'array', 'min:1'],
            'addresses.*.address_type_id' => ['required', 'uuid', 'exists:address_types,id'],
            'addresses.*.address_line_1' => ['required_with:addresses', 'string', 'max:500'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:500'],
            'addresses.*.country_id' => ['required_with:addresses', 'uuid', 'exists:countries,id'],
            'addresses.*.state_id' => ['required', 'uuid', 'exists:states,id'],
            'addresses.*.city' => ['nullable', 'string', 'max:150'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
