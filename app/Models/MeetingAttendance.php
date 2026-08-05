<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dies with its meeting via cascade — no SoftDeletes needed. */
class MeetingAttendance extends Model
{
    use BelongsToCompany;
    use HasUlids;

    protected $guarded = ['id'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(CooperativeMeeting::class, 'cooperative_meeting_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CooperativeMember::class, 'cooperative_member_id');
    }
}
