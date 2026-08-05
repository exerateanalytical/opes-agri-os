<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dies with its vote via cascade — no SoftDeletes needed. */
class VoteBallot extends Model
{
    use BelongsToCompany;
    use HasUlids;

    protected $guarded = ['id'];

    public function vote(): BelongsTo
    {
        return $this->belongsTo(CooperativeVote::class, 'cooperative_vote_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CooperativeMember::class, 'cooperative_member_id');
    }
}
