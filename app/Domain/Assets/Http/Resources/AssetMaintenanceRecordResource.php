<?php

namespace App\Domain\Assets\Http\Resources;

use App\Models\AssetMaintenanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetMaintenanceRecord
 */
class AssetMaintenanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fixed_asset_id' => $this->fixed_asset_id,
            'maintenance_type' => $this->maintenance_type,
            'description' => $this->description,
            'performed_on' => $this->performed_on?->toDateString(),
            'cost' => $this->cost,
            'next_due_on' => $this->next_due_on?->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
