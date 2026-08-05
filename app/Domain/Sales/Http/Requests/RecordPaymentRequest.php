<?php

namespace App\Domain\Sales\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Only the fields a public API caller could legitimately supply.
 * `receipt_number`/`device_id`/`payment_id` on PaymentRecorder::record()
 * exist for a device replaying a payment it took offline — not applicable
 * to a token-authenticated request, so this never passes them through.
 */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('record', Payment::class);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt_format' => ['sometimes', 'string', 'in:a4,a5,thermal58,thermal80'],
            'received_at' => ['nullable', 'date'],
        ];
    }
}
