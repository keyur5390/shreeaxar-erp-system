<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['nullable', 'email', 'max:255'],
            'cc' => ['nullable', 'array', 'max:10'],
            'cc.*' => ['email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return list<string>
     */
    public function ccRecipients(): array
    {
        $cc = $this->input('cc', []);

        if (! is_array($cc)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($email) => is_string($email) ? trim($email) : '',
            $cc
        )));
    }
}
