<?php

namespace App\Domain\Sales\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'reason' => $this->reason,
            'batch_number' => $this->batch_number,
            'expires_on' => $this->expires_on?->toDateString(),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'reference_type' => $this->reference_type ? class_basename($this->reference_type) : null,
            'reference_id' => $this->reference_id,
        ];
    }
}
