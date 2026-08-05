<?php

namespace App\Services\Agri;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\CurrentCompany;
use RuntimeException;

/**
 * Moves a purchase order from draft to issued.
 *
 * Issuing is a commitment, not a receipt — it has no stock or ledger side
 * effect, the same "draft can still change" reasoning that keeps a sale's
 * stock movement tied to the invoice being issued rather than created. The
 * PO number is assigned here, not at creation, so a draft that never goes
 * out never spends a number.
 */
class PurchaseOrderIssuer
{
    public function issue(PurchaseOrder $po, ?User $actor = null): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new RuntimeException('Only a draft purchase order can be issued.');
        }

        if ($po->lines()->count() === 0) {
            throw new RuntimeException('A purchase order needs at least one line before it can be issued.');
        }

        $po->forceFill([
            'number' => $po->number ?: $this->nextNumber(),
            'status' => 'issued',
        ])->save();

        return $po;
    }

    protected function nextNumber(): string
    {
        $company = app(CurrentCompany::class)->get();
        $year = now()->year;

        $count = PurchaseOrder::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->whereNotNull('number')
            ->count();

        return sprintf('PO-%d-%05d', $year, $count + 1);
    }
}
