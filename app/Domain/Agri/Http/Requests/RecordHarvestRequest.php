<?php

namespace App\Domain\Agri\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordHarvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordHarvest', $this->route('cropCycle'));
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expires_on' => ['nullable', 'date'],
        ];
    }
}
