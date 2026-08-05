<?php

namespace App\Domain\Agri\Http\Requests;

use App\Models\CropCycle;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCropCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CropCycle::class);
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'field_id' => ['required', Rule::exists('fields', 'id')->where('company_id', $companyId)],
            'season_id' => ['required', Rule::exists('seasons', 'id')->where('company_id', $companyId)],
            'item_id' => [
                'nullable',
                Rule::exists('items', 'id')->where('company_id', $companyId)->where('type', 'product'),
            ],
            'planned_planting_date' => ['nullable', 'date'],
            'planned_harvest_date' => ['nullable', 'date', 'after_or_equal:planned_planting_date'],
            'planned_yield_qty' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
