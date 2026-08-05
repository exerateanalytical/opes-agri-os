<?php

namespace App\Policies;

class FarmPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'farms';
    }
}
