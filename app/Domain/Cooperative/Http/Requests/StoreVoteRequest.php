<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Models\CooperativeVote;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CooperativeVote::class);
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'cooperative_meeting_id' => [
                'nullable',
                Rule::exists('cooperative_meetings', 'id')->where('company_id', $companyId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weighted' => ['nullable', 'boolean'],
            'opened_on' => ['nullable', 'date'],
        ];
    }
}
