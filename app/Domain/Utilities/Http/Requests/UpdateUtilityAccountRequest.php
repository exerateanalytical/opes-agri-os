<?php

namespace App\Domain\Utilities\Http\Requests;

use App\Models\UtilityAccount;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUtilityAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('utilityAccount'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'farm_id' => ['nullable', Rule::exists('farms', 'id')->where('company_id', $companyId)],
            'utility_type' => ['sometimes', Rule::in(UtilityAccount::TYPES)],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(UtilityAccount::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
