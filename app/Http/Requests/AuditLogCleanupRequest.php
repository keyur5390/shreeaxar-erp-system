<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogCleanupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'older_than_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
