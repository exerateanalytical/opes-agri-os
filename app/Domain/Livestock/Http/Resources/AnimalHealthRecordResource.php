<?php

namespace App\Domain\Livestock\Http\Resources;

use App\Models\AnimalHealthRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnimalHealthRecord
 */
class AnimalHealthRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'record_type' => $this->record_type,
            'description' => $this->description,
            'administered_on' => $this->administered_on?->toDateString(),
            'next_due_on' => $this->next_due_on?->toDateString(),
            'cost' => $this->cost,
            'notes' => $this->notes,
        ];
    }
}
