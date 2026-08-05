<?php

namespace App\Domain\Partners\Http\Requests;

use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('partner'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Partner::TYPES)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(Partner::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
