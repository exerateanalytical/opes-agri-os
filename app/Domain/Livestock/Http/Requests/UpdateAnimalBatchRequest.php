<?php

namespace App\Domain\Livestock\Http\Requests;

use App\Models\AnimalBatch;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnimalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('animalBatch'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'farm_id' => ['nullable', Rule::exists('farms', 'id')->where('company_id', $companyId)],
            'species' => ['sometimes', 'string', 'max:100'],
            'breed' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(AnimalBatch::STATUSES)],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'acquired_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
