<?php

namespace App\Domain\Utilities\Http\Resources;

use App\Models\UtilityAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UtilityAccount
 */
class UtilityAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'utility_type' => $this->utility_type,
            'provider_name' => $this->provider_name,
            'account_number' => $this->account_number,
            'status' => $this->status,
            'notes' => $this->notes,
            'readings' => UtilityReadingResource::collection($this->whenLoaded('readings')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
