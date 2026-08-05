<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordAttendance', $this->route('cooperativeMeeting'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'cooperative_member_id' => [
                'required',
                Rule::exists('cooperative_members', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
