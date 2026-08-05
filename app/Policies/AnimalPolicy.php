<?php

namespace App\Policies;

use App\Models\Animal;
use App\Models\User;

class AnimalPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'livestock';
    }

    public function recordHealth(User $user, Animal $animal): bool
    {
        return $this->owns($animal) && $this->allows($user, 'record-health');
    }

    public function recordProduction(User $user, Animal $animal): bool
    {
        return $this->owns($animal) && $this->allows($user, 'record-production');
    }
}
