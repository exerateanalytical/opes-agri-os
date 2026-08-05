<?php

namespace App\Domain\Cooperative\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisburseLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('disburse', $this->route('loan'));
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'disbursed_on' => ['nullable', 'date'],
        ];
    }
}
