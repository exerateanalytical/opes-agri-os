<?php

namespace App\Domain\Utilities\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUtilityReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordReading', $this->route('utilityAccount'));
    }

    public function rules(): array
    {
        return [
            'read_on' => ['required', 'date'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'consumption' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
