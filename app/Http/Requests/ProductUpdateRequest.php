<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:300'],
            'rate' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'unit_id' => ['sometimes', 'required', 'uuid', 'exists:units,id'],
            'model_number' => ['sometimes', 'nullable', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'keep_image_ids' => ['sometimes', 'string'],
            'primaryImage' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
