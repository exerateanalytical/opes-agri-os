<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Models\CooperativeMember;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCooperativeMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cooperativeMember'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();
        $memberId = $this->route('cooperativeMember')?->id;

        return [
            'membership_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('cooperative_members', 'membership_number')->where('company_id', $companyId)->ignore($memberId),
            ],
            'joined_on' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(CooperativeMember::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
