<?php

namespace App\Policies;

use App\Models\CooperativeMember;
use App\Models\User;

class CooperativeMemberPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'cooperative';
    }

    public function recordContribution(User $user, CooperativeMember $cooperativeMember): bool
    {
        return $this->owns($cooperativeMember) && $this->allows($user, 'record-contribution');
    }
}
