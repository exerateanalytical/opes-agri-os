<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dies with its partner via cascade — no SoftDeletes needed, same as UtilityReading. */
class PartnerInteraction extends Model
{
    use BelongsToCompany;
    use HasUlids;

    public const TYPES = ['meeting', 'call', 'email', 'visit', 'other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'interaction_date' => 'date',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
