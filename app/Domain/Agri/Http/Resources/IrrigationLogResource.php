<?php

namespace App\Domain\Agri\Http\Resources;

use App\Models\IrrigationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IrrigationLog
 */
class IrrigationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_id' => $this->field_id,
            'irrigated_on' => $this->irrigated_on?->toDateString(),
            'method' => $this->method,
            'duration_minutes' => $this->duration_minutes,
            'volume_liters' => $this->volume_liters,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
