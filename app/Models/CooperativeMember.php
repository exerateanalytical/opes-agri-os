<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CooperativeMember extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['active', 'suspended', 'exited'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'balance' => 'decimal:2',
            'vote_weight' => 'decimal:2',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(MemberContribution::class);
    }

    /**
     * Recomputed after every contribution write — the same pattern
     * `Contact::recomputeBalance()` uses, so the figure shown never drifts
     * from what actually got recorded.
     */
    public function recomputeBalance(): void
    {
        $this->forceFill([
            'balance' => $this->contributions()->sum('amount'),
        ])->save();
    }
}
