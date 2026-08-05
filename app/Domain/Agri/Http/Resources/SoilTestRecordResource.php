<?php

namespace App\Domain\Agri\Http\Resources;

use App\Models\SoilTestRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SoilTestRecord
 */
class SoilTestRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_id' => $this->field_id,
            'tested_on' => $this->tested_on?->toDateString(),
            'ph' => $this->ph,
            'nitrogen_ppm' => $this->nitrogen_ppm,
            'phosphorus_ppm' => $this->phosphorus_ppm,
            'potassium_ppm' => $this->potassium_ppm,
            'organic_matter_pct' => $this->organic_matter_pct,
            'recommendations' => $this->recommendations,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
