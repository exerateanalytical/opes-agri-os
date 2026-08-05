<?php

namespace App\Policies;

use App\Models\Field;
use App\Models\User;

class FieldPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'farms';
    }

    public function recordSoilTest(User $user, Field $field): bool
    {
        return $this->owns($field) && $this->allows($user, 'record-soil-test');
    }

    public function recordIrrigation(User $user, Field $field): bool
    {
        return $this->owns($field) && $this->allows($user, 'record-irrigation');
    }
}
