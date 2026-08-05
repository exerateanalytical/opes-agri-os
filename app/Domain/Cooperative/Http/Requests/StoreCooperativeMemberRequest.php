<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Models\CooperativeMember;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCooperativeMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CooperativeMember::class);
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'contact_id' => [
                'required',
                Rule::exists('contacts', 'id')->where('company_id', $companyId),
                Rule::unique('cooperative_members', 'contact_id')->where('company_id', $companyId),
            ],
            'membership_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('cooperative_members', 'membership_number')->where('company_id', $companyId),
            ],
            'joined_on' => ['nullable', 'date'],
            'vote_weight' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
