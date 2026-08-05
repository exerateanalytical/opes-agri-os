<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy extends CompanyScopedPolicy
{
    protected function group(): string
    {
        return 'procurement';
    }

    public function issue(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->owns($purchaseOrder) && $this->allows($user, 'issue');
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->owns($purchaseOrder) && $this->allows($user, 'receive');
    }
}
