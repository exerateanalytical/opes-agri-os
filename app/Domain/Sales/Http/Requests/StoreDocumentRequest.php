<?php

namespace App\Domain\Sales\Http\Requests;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Creates a draft only — issuing (the point a document gets a real number
 * and becomes immutable) is a separate endpoint/ability. Same shape and
 * validation rules as the web composer's inline validation in
 * App\Livewire\Documents\Create::save(), reused via
 * App\Domain\Sales\Actions\CreateDraftDocumentAction.
 */
class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Document::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(DocumentType::class)],
            'contact_id' => ['required', 'string', Rule::exists('contacts', 'id')->where('company_id', app(CurrentCompany::class)->id())],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => [
                'nullable', 'string',
                Rule::exists('items', 'id')->where('company_id', app(CurrentCompany::class)->id()),
            ],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
