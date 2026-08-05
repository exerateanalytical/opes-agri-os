<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Models\CooperativeMeeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cooperativeMeeting'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'scheduled_on' => ['sometimes', 'date'],
            'quorum_required' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(CooperativeMeeting::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
