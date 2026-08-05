<?php

namespace App\Policies;

use App\Models\CropCycle;
use App\Models\User;

class CropCyclePolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'crops';
    }

    public function recordHarvest(User $user, CropCycle $cropCycle): bool
    {
        return $this->owns($cropCycle) && $this->allows($user, 'record-harvest');
    }
}
