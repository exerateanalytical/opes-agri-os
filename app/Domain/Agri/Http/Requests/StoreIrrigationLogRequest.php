<?php

namespace App\Domain\Agri\Http\Requests;

use App\Models\IrrigationLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIrrigationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordIrrigation', $this->route('field'));
    }

    public function rules(): array
    {
        return [
            'irrigated_on' => ['required', 'date'],
            'method' => ['nullable', Rule::in(IrrigationLog::METHODS)],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'volume_liters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
