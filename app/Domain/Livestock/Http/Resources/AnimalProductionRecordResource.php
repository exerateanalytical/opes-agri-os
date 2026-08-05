<?php

namespace App\Domain\Livestock\Http\Resources;

use App\Models\AnimalProductionRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnimalProductionRecord
 */
class AnimalProductionRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'recorded_on' => $this->recorded_on?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
