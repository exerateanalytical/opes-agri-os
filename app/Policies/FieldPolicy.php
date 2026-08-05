<?php

namespace App\Policies;

class FieldPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'farms';
    }
}
