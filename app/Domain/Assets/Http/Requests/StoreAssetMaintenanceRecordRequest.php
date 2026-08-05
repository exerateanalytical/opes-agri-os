<?php

namespace App\Domain\Assets\Http\Requests;

use App\Models\AssetMaintenanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assets.record-maintenance');
    }

    public function rules(): array
    {
        return [
            'maintenance_type' => ['required', Rule::in(AssetMaintenanceRecord::TYPES)],
            'description' => ['required', 'string', 'max:255'],
            'performed_on' => ['required', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'next_due_on' => ['nullable', 'date', 'after_or_equal:performed_on'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
