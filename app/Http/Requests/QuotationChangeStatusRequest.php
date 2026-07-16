<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationChangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_id' => ['required', 'uuid', 'exists:quotation_statuses,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function statusNote(): ?string
    {
        return $this->input('note') ?? $this->input('remarks');
    }
}
