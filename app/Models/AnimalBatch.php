<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A flock recorded as a count, not as individually tagged animals — the
 * poultry-batch counterpart to `Animal`. Kept separate rather than forcing
 * one `animals` row per bird, which nobody managing a few hundred broilers
 * wants to maintain.
 */
class AnimalBatch extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['active', 'sold', 'closed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }
}
