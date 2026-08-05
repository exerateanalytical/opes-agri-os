<?php

namespace App\Domain\Livestock\Http\Resources;

use App\Models\Animal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Animal
 */
class AnimalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'tag_number' => $this->tag_number,
            'species' => $this->species,
            'breed' => $this->breed,
            'sex' => $this->sex,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'status' => $this->status,
            'acquired_on' => $this->acquired_on?->toDateString(),
            'acquisition_cost' => $this->acquisition_cost,
            'notes' => $this->notes,
            'health_records' => AnimalHealthRecordResource::collection($this->whenLoaded('healthRecords')),
            'production_records' => AnimalProductionRecordResource::collection($this->whenLoaded('productionRecords')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
