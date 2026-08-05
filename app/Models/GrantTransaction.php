<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dies with its project via cascade — no SoftDeletes needed, same as
 * UtilityReading. Covers both directions of real cash movement on a grant:
 * a `receipt` (the partner disbursing funds) and an `expenditure` (the
 * project spending them) — both post to the accounting ledger the moment
 * they're recorded. See GrantTransactionRecorder.
 */
class GrantTransaction extends Model
{
    use BelongsToCompany;
    use HasUlids;

    public const TYPES = ['receipt', 'expenditure'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(GrantProject::class, 'grant_project_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
