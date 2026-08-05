<?php

namespace App\Domain\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'method' => $this->method,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'received_at' => $this->received_at?->toIso8601String(),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'document_id' => $allocation->document_id,
                'amount' => $allocation->amount,
            ])),
            'receipt' => ReceiptResource::make($this->whenLoaded('receipt')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
