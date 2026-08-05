<?php

namespace App\Domain\Agri\Http\Resources;

use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Field
 */
class FieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'name' => $this->name,
            'area_hectares' => $this->area_hectares,
            'boundary' => $this->boundary,
            'centroid_lat' => $this->centroid_lat,
            'centroid_lng' => $this->centroid_lng,
            'ownership_type' => $this->ownership_type,
            'lease_start' => $this->lease_start?->toDateString(),
            'lease_end' => $this->lease_end?->toDateString(),
            'lease_notes' => $this->lease_notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
