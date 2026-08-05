<?php

namespace App\Domain\Cooperative\Http\Resources;

use App\Models\CooperativeMeeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CooperativeMeeting
 */
class CooperativeMeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'scheduled_on' => $this->scheduled_on?->toDateString(),
            'quorum_required' => $this->quorum_required,
            'attendance_count' => $this->attendances_count ?? $this->attendances()->count(),
            'quorum_met' => $this->quorumMet(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
