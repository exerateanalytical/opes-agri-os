<?php

namespace App\Domain\Agri\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Once a PO is no longer draft, its lines and supplier are frozen: lines
 * have already been (partially) received against, and `quantity_received`
 * has no meaning against a line that was silently swapped out from under
 * it. Notes/dates remain editable at any status — they carry no downstream
 * state.
 */
class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('purchaseOrder'));
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'supplier_id' => [
                'nullable',
                Rule::exists('contacts', 'id')->where('company_id', $companyId)->where('type', 'supplier'),
            ],
            'order_date' => ['sometimes', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.item_id' => [
                'nullable',
                Rule::exists('items', 'id')->where('company_id', $companyId),
            ],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required_with:lines', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $purchaseOrder = $this->route('purchaseOrder');

            if ($purchaseOrder === null || $purchaseOrder->status === 'draft') {
                return;
            }

            if ($this->has('lines')) {
                $validator->errors()->add(
                    'lines',
                    'This purchase order has already been issued — its lines can no longer be edited.',
                );
            }

            if ($this->has('supplier_id') && (string) $this->input('supplier_id') !== (string) $purchaseOrder->supplier_id) {
                $validator->errors()->add(
                    'supplier_id',
                    'This purchase order has already been issued — its supplier can no longer be changed.',
                );
            }
        });
    }
}
