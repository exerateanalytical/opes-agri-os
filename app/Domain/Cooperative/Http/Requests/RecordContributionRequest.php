<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Models\MemberContribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordContribution', $this->route('cooperativeMember'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(MemberContribution::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'contributed_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
