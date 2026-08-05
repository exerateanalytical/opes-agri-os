<?php

namespace App\Domain\Livestock\Http\Resources;

use App\Models\AnimalBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnimalBatch
 */
class AnimalBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'species' => $this->species,
            'breed' => $this->breed,
            'initial_count' => $this->initial_count,
            'current_count' => $this->current_count,
            'unit_cost' => $this->unit_cost,
            'acquired_on' => $this->acquired_on?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
