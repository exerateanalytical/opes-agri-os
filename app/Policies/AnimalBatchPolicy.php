<?php

namespace App\Policies;

class AnimalBatchPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'livestock';
    }
}
