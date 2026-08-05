<?php

namespace App\Policies;

use App\Models\CooperativeVote;
use App\Models\User;

class CooperativeVotePolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'cooperative';
    }

    public function castVote(User $user, CooperativeVote $cooperativeVote): bool
    {
        return $this->owns($cooperativeVote) && $this->allows($user, 'cast-vote');
    }
}
