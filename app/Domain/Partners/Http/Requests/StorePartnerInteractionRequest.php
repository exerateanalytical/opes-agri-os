<?php

namespace App\Domain\Partners\Http\Requests;

use App\Models\PartnerInteraction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordInteraction', $this->route('partner'));
    }

    public function rules(): array
    {
        return [
            'interaction_date' => ['required', 'date'],
            'type' => ['required', Rule::in(PartnerInteraction::TYPES)],
            'summary' => ['required', 'string'],
        ];
    }
}
