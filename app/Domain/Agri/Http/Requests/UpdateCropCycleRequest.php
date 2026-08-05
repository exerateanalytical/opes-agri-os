<?php

namespace App\Domain\Agri\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plain field edits plus the non-harvest status transitions
 * (planned/planted/growing/closed) — harvesting is its own endpoint
 * (HarvestRecorder), since it also has to write stock, so 'harvested' is
 * never an accepted value here.
 */
class UpdateCropCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cropCycle'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'item_id' => [
                'nullable',
                Rule::exists('items', 'id')->where('company_id', $companyId)->where('type', 'product'),
            ],
            'status' => ['sometimes', Rule::in(['planned', 'planted', 'growing', 'closed'])],
            'growth_stage' => ['nullable', 'string', 'max:100'],
            'planned_planting_date' => ['nullable', 'date'],
            'actual_planting_date' => ['nullable', 'date'],
            'planned_harvest_date' => ['nullable', 'date', 'after_or_equal:planned_planting_date'],
            'planned_yield_qty' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
