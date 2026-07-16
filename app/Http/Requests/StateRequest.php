<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $stateId = $this->route('state');
        $countryId = $this->input('country_id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('states', 'name')
                    ->where(fn ($query) => $query->where('country_id', $countryId))
                    ->ignore($stateId),
            ],
            'country_id' => [
                'required',
                'uuid',
                'exists:countries,id',
            ],
        ];
    }
}
