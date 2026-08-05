<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CooperativeVote extends Model
{
    use BelongsToCompany;
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['open', 'closed'];

    public const CHOICES = ['for', 'against', 'abstain'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opened_on' => 'date',
            'closed_on' => 'date',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(CooperativeMeeting::class, 'cooperative_meeting_id');
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(VoteBallot::class);
    }

    /** @return array{for: int, against: int, abstain: int, total: int} */
    public function tally(): array
    {
        $counts = $this->ballots()
            ->selectRaw('choice, count(*) as total')
            ->groupBy('choice')
            ->pluck('total', 'choice');

        $result = [
            'for' => (int) ($counts['for'] ?? 0),
            'against' => (int) ($counts['against'] ?? 0),
            'abstain' => (int) ($counts['abstain'] ?? 0),
        ];

        return $result + ['total' => array_sum($result)];
    }
}
