<?php

namespace App\Domain\Assets\Http\Resources;

use App\Models\FleetTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FleetTrip
 */
class FleetTripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fixed_asset_id' => $this->fixed_asset_id,
            'driver_name' => $this->driver_name,
            'purpose' => $this->purpose,
            'started_on' => $this->started_on?->toDateString(),
            'ended_on' => $this->ended_on?->toDateString(),
            'start_odometer' => $this->start_odometer,
            'end_odometer' => $this->end_odometer,
            'distance' => $this->distance(),
            'fuel_cost' => $this->fuel_cost,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
