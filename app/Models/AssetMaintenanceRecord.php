<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dies with its asset via cascade — no SoftDeletes needed, same as AnimalHealthRecord. */
class AssetMaintenanceRecord extends Model
{
    use BelongsToCompany;
    use HasUlids;

    public const TYPES = ['service', 'repair', 'inspection'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'cost' => 'decimal:2',
            'next_due_on' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}
