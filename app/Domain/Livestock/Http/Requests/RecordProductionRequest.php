<?php

namespace App\Domain\Livestock\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordProduction', $this->route('animal'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'item_id' => ['nullable', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'recorded_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
