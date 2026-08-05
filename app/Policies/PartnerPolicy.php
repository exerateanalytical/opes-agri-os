<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'partner-crm';
    }

    public function recordInteraction(User $user, Partner $partner): bool
    {
        return $this->owns($partner) && $this->allows($user, 'record-interaction');
    }
}
