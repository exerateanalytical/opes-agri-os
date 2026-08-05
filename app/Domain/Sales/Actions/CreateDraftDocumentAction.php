<?php

namespace App\Domain\Sales\Actions;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\User;
use App\Support\Vat;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds a draft document plus its lines, with tax computed once so the
 * totals and the per-line tax column can never disagree.
 *
 * Extracted from App\Livewire\Documents\Create — the web composer submits
 * the whole form in one call and had this inlined in `save()`. Both it and
 * the API's DocumentController now call this same action, rather than
 * computing VAT and assembling the document twice.
 */
class CreateDraftDocumentAction
{
    /**
     * @param  array{contact_id: string, issue_date: string, due_date?: ?string, notes?: ?string, lines: array<int, array{description: string, quantity: float|string, unit_price: float|string}>}  $data
     */
    public function create(Company $company, User $user, DocumentType $type, array $data): Document
    {
        // Scoped lookup: a contact id from another company fails here rather
        // than silently attaching someone else's customer.
        $contact = Contact::query()->find($data['contact_id']);

        if ($contact === null) {
            throw new RuntimeException('That customer no longer exists.');
        }

        $vat = Vat::forCompany($company, $data['lines']);
        $lines = $this->normalisedLines($data['lines'], $vat['lines']);

        return DB::transaction(function () use ($contact, $company, $lines, $vat, $data, $type, $user) {
            $document = Document::create([
                'type' => $type,
                'contact_id' => $contact->id,
                'status' => DocumentStatus::Draft,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => $company->currency,
                'subtotal' => $vat['subtotal'],
                'tax_total' => $vat['tax_total'],
                'total' => $vat['total'],
                'amount_paid' => 0,
                'balance' => $vat['total'],
                'notes' => ($data['notes'] ?? null) ?: null,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $index => $line) {
                DocumentLine::create($line + [
                    'document_id' => $document->id,
                    'unit' => 'unit',
                    'sort_order' => $index,
                ]);
            }

            return $document;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, array{net: float, tax: float, gross: float, unit_net: float}>  $taxed
     * @return array<int, array<string, mixed>>
     */
    protected function normalisedLines(array $lines, array $taxed): array
    {
        return collect($lines)
            ->map(fn (array $line, int $index) => [
                'description' => trim((string) $line['description']),
                'quantity' => (float) $line['quantity'],
                // Always stored net of tax, whichever way it was keyed, so a
                // line means the same thing on every document regardless of
                // whether the business quotes HT or TTC.
                'unit_price' => $taxed[$index]['unit_net'] ?? (float) $line['unit_price'],
                'tax_amount' => $taxed[$index]['tax'] ?? 0.0,
                'line_total' => $taxed[$index]['net'] ?? 0.0,
            ])
            ->values()
            ->all();
    }
}
