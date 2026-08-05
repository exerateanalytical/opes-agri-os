<?php

namespace App\Domain\Livestock\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustAnimalBatchCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('animalBatch'));
    }

    public function rules(): array
    {
        return [
            // Negative for losses (mortality, sales), positive for hatching/additions.
            'change' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
