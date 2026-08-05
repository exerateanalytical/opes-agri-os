<?php

namespace App\Domain\Agri\Http\Requests;

use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Farm::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'size_hectares' => ['nullable', 'numeric', 'min:0'],
            'ownership_type' => ['sometimes', Rule::in(['owned', 'leased', 'mixed'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
