<?php

namespace App\Services\Agri;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves a purchase order from draft to issued.
 *
 * Issuing is a commitment, not a receipt — it has no stock or ledger side
 * effect, the same "draft can still change" reasoning that keeps a sale's
 * stock movement tied to the invoice being issued rather than created. The
 * PO number is assigned here, not at creation, so a draft that never goes
 * out never spends a number.
 *
 * Numbering mirrors the guarantee `DocumentNumbers` gives Sales: the
 * count-then-format read happens inside a transaction that locks the
 * company's already-numbered rows for the year, so two concurrent `issue()`
 * calls can't both read the same count. `purchase_orders` has no lease
 * ledger of its own (unlike `DocumentNumbers`' `NumberLease`, built for
 * offline device numbering that POs don't need), so as a second line of
 * defence the existing `(company_id, number)` unique constraint is relied
 * on too — a collision that slips past the lock still fails the insert
 * instead of silently duplicating, and is retried once.
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

        if ($po->number) {
            $po->forceFill(['status' => 'issued'])->save();

            return $po;
        }

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return DB::transaction(function () use ($po) {
                    $po->forceFill([
                        'number' => $this->nextNumber(),
                        'status' => 'issued',
                    ])->save();

                    return $po;
                });
            } catch (QueryException $e) {
                if ($attempt === 2 || ! $this->isUniqueViolation($e)) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Could not assign a purchase order number.');
    }

    /**
     * Must run inside the same transaction as the save that consumes the
     * number: `lockForUpdate()` blocks a second concurrent caller from
     * reading the same count until this transaction commits (or rolls
     * back) the row it assigns the number to.
     */
    protected function nextNumber(): string
    {
        $company = app(CurrentCompany::class)->get();
        $year = now()->year;

        $count = PurchaseOrder::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->whereNotNull('number')
            ->lockForUpdate()
            ->count();

        return sprintf('PO-%d-%05d', $year, $count + 1);
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
