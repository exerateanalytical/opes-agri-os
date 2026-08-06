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

    /**
     * Ids of this animal's descendants, walked breadth-first through both the
     * sire and dam lines, bounded to $maxDepth generations. Used to reject a
     * parentage edit that would make one of an animal's own descendants its
     * ancestor — an actual biological impossibility, and a cycle our
     * ancestry queries (and any future pedigree reporting) can't tolerate.
     *
     * Bounded rather than a full recursive CTE: this schema/DB combination
     * doesn't guarantee recursive query support, and real-world pedigrees
     * a business tracks by hand rarely run past a handful of generations.
     *
     * @return array<int, string>
     */
    public function descendantIds(int $maxDepth = 6): array
    {
        $descendants = [];
        $frontier = [$this->id];

        for ($depth = 0; $depth < $maxDepth && $frontier !== []; $depth++) {
            $children = self::query()
                ->where('company_id', $this->company_id)
                ->where(function ($query) use ($frontier) {
                    $query->whereIn('sire_id', $frontier)->orWhereIn('dam_id', $frontier);
                })
                ->pluck('id')
                ->all();

            $children = array_values(array_diff($children, $descendants));

            if ($children === []) {
                break;
            }

            $descendants = array_merge($descendants, $children);
            $frontier = $children;
        }

        return $descendants;
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
