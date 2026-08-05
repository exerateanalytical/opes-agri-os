<?php

namespace App\Services\Agri;

use App\Models\CropCycle;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a crop cycle's harvest into stock, through the same
 * `StockLedger::receive()` every other delivery goes through — a harvest is
 * inventory arriving, no different from a purchase, except at zero cash cost.
 *
 * Deliberately does not post to the accounting ledger: a harvest owes
 * nothing to anyone, which the existing RecordsBusinessEvents/Ledger pattern
 * has no event for yet. Recognising the harvest's value on the books is an
 * open question deferred past V1 — see
 * docs/architecture/agri-platform-roadmap.md.
 */
class HarvestRecorder
{
    public function __construct(protected StockLedger $stock) {}

    /**
     * @param  array{batch_number?: ?string, expires_on?: ?string}  $options
     */
    public function record(CropCycle $cycle, User $actor, float $quantity, ?float $unitCost = null, array $options = []): CropCycle
    {
        if ($cycle->status === 'harvested' || $cycle->status === 'closed') {
            throw new RuntimeException('This crop cycle has already been harvested.');
        }

        if ($quantity <= 0) {
            throw new RuntimeException('Harvest quantity must be greater than zero.');
        }

        $cycle->loadMissing('item');

        if ($cycle->item === null) {
            throw new RuntimeException('This crop cycle has no crop (item) to harvest into stock.');
        }

        return DB::transaction(function () use ($cycle, $actor, $quantity, $unitCost, $options) {
            $company = app(CurrentCompany::class)->get();

            $this->stock->receive(
                company: $company,
                item: $cycle->item,
                quantity: $quantity,
                unitCost: $unitCost,
                actor: $actor,
                reason: 'harvest',
                batchNumber: $options['batch_number'] ?? null,
                expiresOn: $options['expires_on'] ?? null,
                referenceType: CropCycle::class,
                referenceId: $cycle->id,
            );

            $cycle->forceFill([
                'actual_yield_qty' => $quantity,
                'yield_unit' => $cycle->item->unit,
                'actual_harvest_date' => now()->toDateString(),
                'status' => 'harvested',
            ])->save();

            return $cycle;
        });
    }
}
