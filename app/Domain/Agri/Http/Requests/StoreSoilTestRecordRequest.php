<?php

namespace App\Domain\Agri\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSoilTestRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordSoilTest', $this->route('field'));
    }

    public function rules(): array
    {
        return [
            'tested_on' => ['required', 'date'],
            'ph' => ['nullable', 'numeric', 'between:0,14'],
            'nitrogen_ppm' => ['nullable', 'numeric', 'min:0'],
            'phosphorus_ppm' => ['nullable', 'numeric', 'min:0'],
            'potassium_ppm' => ['nullable', 'numeric', 'min:0'],
            'organic_matter_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recommendations' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
