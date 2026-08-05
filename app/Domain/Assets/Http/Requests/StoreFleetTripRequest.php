<?php

namespace App\Domain\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFleetTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assets.record-trip');
    }

    public function rules(): array
    {
        return [
            'driver_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'started_on' => ['required', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'start_odometer' => ['nullable', 'numeric', 'min:0'],
            'end_odometer' => ['nullable', 'numeric', 'min:0', 'gte:start_odometer'],
            'fuel_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
