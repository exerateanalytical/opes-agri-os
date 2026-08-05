<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CooperativeMeeting extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['scheduled', 'held', 'cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(CooperativeVote::class);
    }

    /** Whether enough members showed up for this meeting to decide anything. */
    public function quorumMet(): bool
    {
        return $this->attendances()->count() >= $this->quorum_required;
    }
}
