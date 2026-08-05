<?php

namespace App\Domain\Livestock\Http\Requests;

use App\Models\AnimalBatch;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnimalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AnimalBatch::class);
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'farm_id' => ['nullable', Rule::exists('farms', 'id')->where('company_id', $companyId)],
            'species' => ['required', 'string', 'max:100'],
            'breed' => ['nullable', 'string', 'max:100'],
            'initial_count' => ['required', 'integer', 'min:1'],
            'acquired_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
