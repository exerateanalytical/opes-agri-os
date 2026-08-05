<?php

namespace App\Domain\Agri\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('purchaseOrder'));
    }

    public function rules(): array
    {
        return [
            'received_on' => ['nullable', 'date'],
            'location_id' => ['nullable', 'string'],
            'record_expense' => ['nullable', 'boolean'],
            'vat_rate' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:100'],
            'lines.*.expires_on' => ['nullable', 'date'],
        ];
    }
}
