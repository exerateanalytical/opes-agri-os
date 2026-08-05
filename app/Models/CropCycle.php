<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CropCycle extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['planned', 'planted', 'growing', 'harvested', 'closed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'planned_planting_date' => 'date',
            'actual_planting_date' => 'date',
            'planned_harvest_date' => 'date',
            'actual_harvest_date' => 'date',
            'planned_yield_qty' => 'decimal:3',
            'actual_yield_qty' => 'decimal:3',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** The crop being grown — an Item so its harvest is stock the business already knows how to sell. */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
