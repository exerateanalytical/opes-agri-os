<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dies with its field via cascade — no SoftDeletes needed, same as AnimalHealthRecord. */
class IrrigationLog extends Model
{
    use BelongsToCompany;
    use HasUlids;

    public const METHODS = ['drip', 'sprinkler', 'flood', 'manual'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'irrigated_on' => 'date',
            'volume_liters' => 'decimal:2',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
