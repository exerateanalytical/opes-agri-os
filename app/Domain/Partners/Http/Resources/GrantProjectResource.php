<?php

namespace App\Domain\Partners\Http\Resources;

use App\Models\GrantProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GrantProject
 */
class GrantProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'name' => $this->name,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'received_amount' => $this->received_amount,
            'spent_amount' => $this->spent_amount,
            'balance' => $this->balance(),
            'transactions' => GrantTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
