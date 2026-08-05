<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Models\Loan;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Loan::class);
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'cooperative_member_id' => [
                'required',
                Rule::exists('cooperative_members', 'id')->where('company_id', $companyId),
            ],
            'principal' => ['required', 'numeric', 'gt:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'term_months' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
