<?php

namespace App\Domain\Livestock\Http\Resources;

use App\Models\AnimalBatchAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnimalBatchAdjustment
 */
class AnimalBatchAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'animal_batch_id' => $this->animal_batch_id,
            'change' => $this->change,
            'resulting_count' => $this->resulting_count,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
