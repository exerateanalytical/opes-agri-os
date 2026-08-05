<?php

namespace App\Domain\Livestock\Http\Requests;

use App\Models\Animal;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnimalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('animal'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'farm_id' => ['nullable', Rule::exists('farms', 'id')->where('company_id', $companyId)],
            'tag_number' => ['nullable', 'string', 'max:100'],
            'species' => ['sometimes', 'string', 'max:100'],
            'breed' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(Animal::STATUSES)],
            'acquired_on' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
