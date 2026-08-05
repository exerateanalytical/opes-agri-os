<?php

namespace App\Policies;

class SeasonPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'farms';
    }
}
