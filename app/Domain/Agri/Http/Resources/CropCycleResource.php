<?php

namespace App\Domain\Agri\Http\Resources;

use App\Models\CropCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CropCycle
 */
class CropCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_id' => $this->field_id,
            'season_id' => $this->season_id,
            'item_id' => $this->item_id,
            'status' => $this->status,
            'growth_stage' => $this->growth_stage,
            'planned_planting_date' => $this->planned_planting_date?->toDateString(),
            'actual_planting_date' => $this->actual_planting_date?->toDateString(),
            'planned_harvest_date' => $this->planned_harvest_date?->toDateString(),
            'actual_harvest_date' => $this->actual_harvest_date?->toDateString(),
            'planned_yield_qty' => $this->planned_yield_qty,
            'actual_yield_qty' => $this->actual_yield_qty,
            'yield_unit' => $this->yield_unit,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
