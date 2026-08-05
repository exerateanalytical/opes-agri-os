<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UtilityAccount;

class UtilityAccountPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'utilities';
    }

    public function recordReading(User $user, UtilityAccount $utilityAccount): bool
    {
        return $this->owns($utilityAccount) && $this->allows($user, 'record-reading');
    }
}
