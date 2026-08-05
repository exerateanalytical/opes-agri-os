<?php

namespace App\Domain\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Contact
 */
class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phones' => $this->phones ?? [],
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'tax_id' => $this->tax_id,
            'credit_limit' => $this->credit_limit,
            'payment_terms_days' => $this->payment_terms_days,
            'notes' => $this->notes,
            'tags' => $this->tags ?? [],
            'balance' => $this->balance,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
