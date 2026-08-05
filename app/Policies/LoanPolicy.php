<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'cooperative';
    }

    public function disburse(User $user, Loan $loan): bool
    {
        return $this->owns($loan) && $this->allows($user, 'disburse-loan');
    }

    public function recordRepayment(User $user, Loan $loan): bool
    {
        return $this->owns($loan) && $this->allows($user, 'record-repayment');
    }
}
