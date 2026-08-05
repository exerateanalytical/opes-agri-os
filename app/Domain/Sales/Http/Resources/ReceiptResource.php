<?php

namespace App\Domain\Sales\Http\Resources;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Receipt
 */
class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'format' => $this->format,
            'total' => $this->total,
            'currency' => $this->currency,
            'status' => $this->status,
            'hash' => $this->content_hash,
            'verification_url' => $this->when(
                $this->verification_token_id !== null,
                fn () => route('verification.show', $this->verificationToken->token),
            ),
            'issued_at' => $this->issued_at?->toIso8601String(),
        ];
    }
}
