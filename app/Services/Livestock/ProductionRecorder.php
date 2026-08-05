<?php

namespace App\Services\Livestock;

use App\Models\Animal;
use App\Models\AnimalProductionRecord;
use App\Models\Item;
use App\Models\User;
use App\Services\Accounting\RecordsBusinessEvents;
use App\Services\Stock\StockLedger;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns what an animal produced (milk, eggs, wool) into stock, through the
 * same `StockLedger::receive()` a crop harvest uses — production is
 * inventory arriving, no different from a harvest, except the source is an
 * animal rather than a field.
 *
 * Posts to the accounting ledger when a unit cost is given, the same
 * `recordStockValuation()` a crop harvest now uses — see
 * `App\Services\Agri\HarvestRecorder` and
 * docs/architecture/agri-platform-roadmap.md.
 */
class ProductionRecorder
{
    public function __construct(protected StockLedger $stock, protected RecordsBusinessEvents $events) {}

    /**
     * @param  array{notes?: ?string, unit_cost?: ?float}  $options
     */
    public function record(Animal $animal, User $actor, ?Item $item, float $quantity, ?string $recordedOn = null, array $options = []): AnimalProductionRecord
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Production quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($animal, $actor, $item, $quantity, $recordedOn, $options) {
            $company = app(CurrentCompany::class)->get();

            $record = $animal->productionRecords()->create([
                'item_id' => $item?->id,
                'quantity' => $quantity,
                'unit' => $item?->unit,
                'recorded_on' => $recordedOn ?? now()->toDateString(),
                'notes' => $options['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $unitCost = $options['unit_cost'] ?? null;

            if ($item !== null && $item->track_stock) {
                $this->stock->receive(
                    company: $company,
                    item: $item,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    actor: $actor,
                    reason: 'livestock-production',
                    occurredAt: $recordedOn,
                    referenceType: AnimalProductionRecord::class,
                    referenceId: $record->id,
                );
            }

            if ($unitCost !== null && $unitCost > 0) {
                $this->events->recordQuietly(fn () => $this->events->recordStockValuation(
                    source: $record,
                    company: $company,
                    amount: $quantity * $unitCost,
                    narration: 'Production animale — '.($animal->tag_number ?? $animal->species ?? ''),
                    entryDate: $record->recorded_on?->toDateString(),
                    actor: $actor,
                ));
            }

            return $record;
        });
    }
}
