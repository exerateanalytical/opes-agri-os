<?php

namespace App\Domain\Partners\Http\Resources;

use App\Models\GrantTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GrantTransaction
 */
class GrantTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grant_project_id' => $this->grant_project_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'method' => $this->method,
            'description' => $this->description,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
