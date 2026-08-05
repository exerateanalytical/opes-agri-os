<?php

namespace App\Policies;

use App\Models\GrantProject;
use App\Models\User;

class GrantProjectPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'grants';
    }

    public function recordTransaction(User $user, GrantProject $grantProject): bool
    {
        return $this->owns($grantProject) && $this->allows($user, 'record-transaction');
    }
}
