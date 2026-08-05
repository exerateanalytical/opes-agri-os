<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UtilityAccount extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const TYPES = ['electricity', 'water', 'internet', 'gas', 'other'];

    public const STATUSES = ['active', 'inactive'];

    protected $guarded = ['id'];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(UtilityReading::class);
    }
}
