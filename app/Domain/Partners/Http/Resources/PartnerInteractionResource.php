<?php

namespace App\Domain\Partners\Http\Resources;

use App\Models\PartnerInteraction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PartnerInteraction
 */
class PartnerInteractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'interaction_date' => $this->interaction_date?->toDateString(),
            'type' => $this->type,
            'summary' => $this->summary,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
