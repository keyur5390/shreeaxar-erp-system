<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:300'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'unit_id' => ['required', 'uuid', 'exists:units,id'],
            'model_number' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'primaryImage' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
