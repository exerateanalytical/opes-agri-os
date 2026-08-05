<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Animal extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['active', 'sold', 'deceased', 'culled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'acquired_on' => 'date',
            'acquisition_cost' => 'decimal:2',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sire_id');
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dam_id');
    }

    public function offspringAsSire(): HasMany
    {
        return $this->hasMany(self::class, 'sire_id');
    }

    public function offspringAsDam(): HasMany
    {
        return $this->hasMany(self::class, 'dam_id');
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(AnimalHealthRecord::class);
    }

    public function productionRecords(): HasMany
    {
        return $this->hasMany(AnimalProductionRecord::class);
    }
}
