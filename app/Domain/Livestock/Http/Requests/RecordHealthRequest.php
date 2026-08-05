<?php

namespace App\Domain\Livestock\Http\Requests;

use App\Models\AnimalHealthRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordHealthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordHealth', $this->route('animal'));
    }

    public function rules(): array
    {
        return [
            'record_type' => ['required', Rule::in(AnimalHealthRecord::TYPES)],
            'description' => ['required', 'string', 'max:255'],
            'administered_on' => ['required', 'date'],
            'next_due_on' => ['nullable', 'date', 'after_or_equal:administered_on'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
