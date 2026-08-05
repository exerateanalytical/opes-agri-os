<?php

namespace App\Domain\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `amount` present + source is an issued invoice → a partial credit note
 * (DocumentConverter::creditNote); otherwise a whole-document conversion
 * (DocumentConverter::convert) — quote to invoice, invoice to a full credit
 * note, work order to invoice. Mirrors DocumentConverter::canConvert()'s own
 * rules, which the controller still re-checks — this only shapes the input.
 */
class ConvertDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('convert', $this->route('document'));
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
