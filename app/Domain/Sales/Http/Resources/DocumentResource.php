<?php

namespace App\Domain\Sales\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'number' => $this->number,
            'status' => $this->status,
            'contact_id' => $this->contact_id,
            'contact' => ContactResource::make($this->whenLoaded('contact')),
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'balance' => $this->balance,
            'notes' => $this->notes,
            'reference' => $this->reference,
            'parent_document_id' => $this->parent_document_id,
            'lines' => DocumentLineResource::collection($this->whenLoaded('lines')),
            // Server-computed, output-only — never accepted back from a
            // client, same principle App\Services\SyncEngine::sanitise()
            // applies to every server-owned field.
            'hash' => $this->when($this->content_hash !== null, $this->content_hash),
            'verification_url' => $this->when(
                $this->verification_token_id !== null,
                fn () => route('verification.show', $this->verificationToken->token),
            ),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
