<?php

namespace App\Domain\Cooperative\Http\Resources;

use App\Models\LoanRepayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanRepayment
 */
class LoanRepaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'principal_portion' => $this->principal_portion,
            'interest_portion' => $this->interest_portion,
            'method' => $this->method?->value,
            'paid_on' => $this->paid_on?->toDateString(),
            'reference' => $this->reference,
            'notes' => $this->notes,
        ];
    }
}
