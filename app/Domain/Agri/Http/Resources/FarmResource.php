<?php

namespace App\Domain\Agri\Http\Resources;

use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Farm
 */
class FarmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location_text' => $this->location_text,
            'size_hectares' => $this->size_hectares,
            'ownership_type' => $this->ownership_type,
            'notes' => $this->notes,
            'fields_count' => $this->whenCounted('fields'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
