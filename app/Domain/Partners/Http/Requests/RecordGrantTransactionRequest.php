<?php

namespace App\Domain\Partners\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\GrantTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordGrantTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordTransaction', $this->route('grantProject'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(GrantTransaction::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'transaction_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ];
    }
}
