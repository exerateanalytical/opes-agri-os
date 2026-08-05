<?php

namespace App\Domain\Cooperative\Http\Resources;

use App\Models\MemberContribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MemberContribution
 */
class MemberContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'contributed_on' => $this->contributed_on?->toDateString(),
            'reference' => $this->reference,
            'notes' => $this->notes,
        ];
    }
}
