<?php

namespace App\Domain\Cooperative\Http\Resources;

use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Loan
 */
class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cooperative_member_id' => $this->cooperative_member_id,
            'principal' => $this->principal,
            'interest_rate' => $this->interest_rate,
            'total_interest' => $this->total_interest,
            'total_repayable' => $this->total_repayable,
            'balance' => $this->balance,
            'term_months' => $this->term_months,
            'status' => $this->status,
            'disbursed_on' => $this->disbursed_on?->toDateString(),
            'notes' => $this->notes,
            'repayments' => LoanRepaymentResource::collection($this->whenLoaded('repayments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
