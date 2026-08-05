<?php

namespace App\Domain\Cooperative\Http\Resources;

use App\Models\CooperativeMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CooperativeMember
 */
class CooperativeMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'membership_number' => $this->membership_number,
            'joined_on' => $this->joined_on?->toDateString(),
            'status' => $this->status,
            'balance' => $this->balance,
            'vote_weight' => $this->vote_weight,
            'notes' => $this->notes,
            'contributions' => MemberContributionResource::collection($this->whenLoaded('contributions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
