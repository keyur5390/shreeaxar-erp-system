<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'department_id' => ['sometimes', 'nullable', 'uuid', 'exists:departments,id'],
            'role_id' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('roles', 'id')->where('guard_name', 'api'),
            ],
            'profile_image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'addresses' => ['sometimes', 'array'],
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
