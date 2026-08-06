<?php

namespace App\Domain\Utilities\Http\Requests;

use App\Domain\Utilities\Support\ReadingConsistency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

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
            'meter_reset' => ['nullable', 'boolean'],
            'consumption' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->validated();

            try {
                ReadingConsistency::check(
                    account: $this->route('utilityAccount'),
                    readOn: $data['read_on'],
                    meterReading: isset($data['meter_reading']) ? (float) $data['meter_reading'] : null,
                    meterReset: (bool) ($data['meter_reset'] ?? false),
                );
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }
}
