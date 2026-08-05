<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Models\CooperativeVote;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CastVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('castVote', $this->route('cooperativeVote'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'cooperative_member_id' => [
                'required',
                Rule::exists('cooperative_members', 'id')->where('company_id', $companyId),
            ],
            'choice' => ['required', Rule::in(CooperativeVote::CHOICES)],
        ];
    }
}
