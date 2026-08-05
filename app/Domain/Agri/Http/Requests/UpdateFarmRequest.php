<?php

namespace App\Domain\Agri\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('farm'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'size_hectares' => ['nullable', 'numeric', 'min:0'],
            'ownership_type' => ['sometimes', Rule::in(['owned', 'leased', 'mixed'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
