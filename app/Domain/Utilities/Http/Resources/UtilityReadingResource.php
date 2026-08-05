<?php

namespace App\Domain\Utilities\Http\Resources;

use App\Models\UtilityReading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UtilityReading
 */
class UtilityReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'read_on' => $this->read_on?->toDateString(),
            'meter_reading' => $this->meter_reading,
            'consumption' => $this->consumption,
            'cost' => $this->cost,
            'notes' => $this->notes,
        ];
    }
}
