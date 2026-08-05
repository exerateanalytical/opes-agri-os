<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Models\CooperativeMeeting;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CooperativeMeeting::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'scheduled_on' => ['required', 'date'],
            'quorum_required' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
