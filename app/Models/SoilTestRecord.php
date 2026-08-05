<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dies with its field via cascade — no SoftDeletes needed, same as AnimalHealthRecord. */
class SoilTestRecord extends Model
{
    use BelongsToCompany;
    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tested_on' => 'date',
            'ph' => 'decimal:2',
            'nitrogen_ppm' => 'decimal:2',
            'phosphorus_ppm' => 'decimal:2',
            'potassium_ppm' => 'decimal:2',
            'organic_matter_pct' => 'decimal:2',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
