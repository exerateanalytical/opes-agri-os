<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dies with its asset via cascade — no SoftDeletes needed, same as AssetMaintenanceRecord. */
class FleetTrip extends Model
{
    use BelongsToCompany;
    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'start_odometer' => 'decimal:1',
            'end_odometer' => 'decimal:1',
            'fuel_cost' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    /** Distance covered, when both readings were taken. */
    public function distance(): ?float
    {
        if ($this->start_odometer === null || $this->end_odometer === null) {
            return null;
        }

        return round((float) $this->end_odometer - (float) $this->start_odometer, 1);
    }
}
