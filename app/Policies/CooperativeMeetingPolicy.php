<?php

namespace App\Policies;

use App\Models\CooperativeMeeting;
use App\Models\User;

class CooperativeMeetingPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'cooperative';
    }

    public function recordAttendance(User $user, CooperativeMeeting $cooperativeMeeting): bool
    {
        return $this->owns($cooperativeMeeting) && $this->allows($user, 'record-attendance');
    }
}
