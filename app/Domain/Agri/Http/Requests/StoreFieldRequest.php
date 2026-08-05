<?php

namespace App\Domain\Agri\Http\Requests;

use App\Models\Field;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Field::class);
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'farm_id' => ['required', Rule::exists('farms', 'id')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'area_hectares' => ['nullable', 'numeric', 'min:0'],
            'boundary' => ['nullable', 'array'],
            'boundary.*.lat' => ['required_with:boundary', 'numeric', 'between:-90,90'],
            'boundary.*.lng' => ['required_with:boundary', 'numeric', 'between:-180,180'],
            'centroid_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'centroid_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'ownership_type' => ['sometimes', Rule::in(['owned', 'leased'])],
            'lease_start' => ['nullable', 'date'],
            'lease_end' => ['nullable', 'date', 'after_or_equal:lease_start'],
            'lease_notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
