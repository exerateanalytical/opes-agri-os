<?php

namespace App\Domain\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Draft-only metadata edits. Line/amount changes go through void-and-reissue
 * or a credit note once a document is issued — the Document model itself
 * enforces that (see Document::booted()'s `updating` guard) by rejecting any
 * dirty attribute outside a small mutable set, which is what turns an
 * attempted edit on a non-draft document into the 409 this endpoint returns
 * rather than silently accepting it.
 */
class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('document'));
    }

    public function rules(): array
    {
        return [
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
