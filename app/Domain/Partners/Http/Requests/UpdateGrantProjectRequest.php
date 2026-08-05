<?php

namespace App\Domain\Partners\Http\Requests;

use App\Models\GrantProject;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrantProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('grantProject'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'partner_id' => ['nullable', Rule::exists('partners', 'id')->where('company_id', $companyId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(GrantProject::STATUSES)],
        ];
    }
}
