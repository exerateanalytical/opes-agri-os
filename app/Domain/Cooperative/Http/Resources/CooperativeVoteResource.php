<?php

namespace App\Domain\Cooperative\Http\Resources;

use App\Models\CooperativeVote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CooperativeVote
 */
class CooperativeVoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cooperative_meeting_id' => $this->cooperative_meeting_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'weighted' => $this->weighted,
            'opened_on' => $this->opened_on?->toDateString(),
            'closed_on' => $this->closed_on?->toDateString(),
            'tally' => $this->tally(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
