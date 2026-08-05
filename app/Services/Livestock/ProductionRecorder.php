<?php

namespace App\Services\Livestock;

use App\Models\Animal;
use App\Models\AnimalProductionRecord;
use App\Models\Item;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns what an animal produced (milk, eggs, wool) into stock, through the
 * same `StockLedger::receive()` a crop harvest uses — production is
 * inventory arriving, no different from a harvest, except the source is an
 * animal rather than a field.
 */
class ProductionRecorder
{
    public function __construct(protected StockLedger $stock) {}

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

            if ($item !== null && $item->track_stock) {
                $this->stock->receive(
                    company: $company,
                    item: $item,
                    quantity: $quantity,
                    unitCost: $options['unit_cost'] ?? null,
                    actor: $actor,
                    reason: 'livestock-production',
                    occurredAt: $recordedOn,
                    referenceType: AnimalProductionRecord::class,
                    referenceId: $record->id,
                );
            }

            return $record;
        });
    }
}
