<?php

namespace App\Services\Agri;

use App\Models\CropCycle;
use App\Models\User;
use App\Services\Accounting\RecordsBusinessEvents;
use App\Services\Stock\StockLedger;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a crop cycle's harvest into stock, through the same
 * `StockLedger::receive()` every other delivery goes through — a harvest is
 * inventory arriving, no different from a purchase, except at zero cash cost.
 *
 * Posts to the accounting ledger when a unit cost is given, resolving the
 * harvest-valuation question the roadmap carried from V1 into V3 — see
 * `RecordsBusinessEvents::recordStockValuation()` and
 * docs/architecture/agri-platform-roadmap.md. A harvest with no known cost
 * stays unposted exactly as before: there is nothing to value it at.
 */
class HarvestRecorder
{
    public function __construct(protected StockLedger $stock, protected RecordsBusinessEvents $events) {}

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

            if ($unitCost !== null && $unitCost > 0) {
                $this->events->recordQuietly(fn () => $this->events->recordStockValuation(
                    source: $cycle,
                    company: $company,
                    amount: $quantity * $unitCost,
                    narration: 'Récolte — '.($cycle->item->name ?? ''),
                    entryDate: $cycle->actual_harvest_date?->toDateString(),
                    actor: $actor,
                ));
            }

            return $cycle;
        });
    }
}
