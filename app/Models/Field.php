<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A plot within a farm. `boundary` is a plain JSON array of {lat,lng}
 * coordinates for a Leaflet display — see
 * docs/architecture/agri-platform-roadmap.md for why this isn't a MySQL
 * spatial type: no spatial queries are in scope, just storage and display.
 */
class Field extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'boundary' => 'array',
            'area_hectares' => 'decimal:2',
            'centroid_lat' => 'decimal:7',
            'centroid_lng' => 'decimal:7',
            'lease_start' => 'date',
            'lease_end' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }
}
