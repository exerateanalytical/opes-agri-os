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

    /**
     * A plain headcount by default — one ballot, one vote, same as before
     * this column existed. When `weighted` is set, each ballot counts for
     * its member's `vote_weight` instead of 1, the share/patronage-weighted
     * reading a cooperative's bylaws can call for on a given resolution.
     *
     * @return array{for: float|int, against: float|int, abstain: float|int, total: float|int}
     */
    public function tally(): array
    {
        if (! $this->weighted) {
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

        $counts = $this->ballots()
            ->join('cooperative_members', 'cooperative_members.id', '=', 'vote_ballots.cooperative_member_id')
            ->selectRaw('choice, sum(cooperative_members.vote_weight) as total')
            ->groupBy('choice')
            ->pluck('total', 'choice');

        $result = [
            'for' => (float) ($counts['for'] ?? 0),
            'against' => (float) ($counts['against'] ?? 0),
            'abstain' => (float) ($counts['abstain'] ?? 0),
        ];

        return $result + ['total' => array_sum($result)];
    }
}
