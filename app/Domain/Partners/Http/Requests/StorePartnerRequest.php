<?php

namespace App\Domain\Partners\Http\Requests;

use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Partner::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Partner::TYPES)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
