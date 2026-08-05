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
        // A PATCH may send only one of the two dates. Comparing against the
        // stored value for whichever one is missing from this request means
        // "due 3 days after issue" can't be rejected as "before issue" just
        // because only due_date was submitted this time, and a bare due_date
        // patch is still checked against the issue_date already on file.
        $document = $this->route('document');
        $issueDate = $this->input('issue_date', $document?->issue_date?->toDateString());

        return [
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:'.($issueDate ?? 'today')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
