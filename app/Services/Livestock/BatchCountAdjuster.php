<?php

namespace App\Services\Livestock;

use App\Models\AnimalBatch;
use App\Models\User;
use App\Services\Accounting\RecordsBusinessEvents;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Adjusts a flock's running count — mortality, culling, sales, hatching —
 * without ever letting it go negative, recording each change as its own
 * `AnimalBatchAdjustment` row rather than just moving the running total, so
 * a business can reconstruct when and why a loss happened, not only how
 * many are left today.
 *
 * A negative change (mortality, culling, non-sale loss) posts to the
 * accounting ledger when the batch has a known `unit_cost`, the same
 * "value it if we can price it" rule the harvest and production valuations
 * follow — see `RecordsBusinessEvents::recordBatchLoss()`.
 */
class BatchCountAdjuster
{
    public function __construct(protected RecordsBusinessEvents $events) {}

    public function adjust(AnimalBatch $batch, int $change, ?User $actor = null, ?string $reason = null): AnimalBatch
    {
        $newCount = $batch->current_count + $change;

        if ($newCount < 0) {
            throw new RuntimeException('This would take the batch below zero.');
        }

        return DB::transaction(function () use ($batch, $change, $newCount, $actor, $reason) {
            $company = app(CurrentCompany::class)->get();

            $batch->forceFill(['current_count' => $newCount])->save();

            $adjustment = $batch->adjustments()->create([
                'change' => $change,
                'resulting_count' => $newCount,
                'reason' => $reason,
                'created_by' => $actor?->id,
            ]);

            if ($change < 0 && $batch->unit_cost !== null && (float) $batch->unit_cost > 0) {
                $this->events->recordQuietly(fn () => $this->events->recordBatchLoss(
                    source: $adjustment,
                    company: $company,
                    amount: abs($change) * (float) $batch->unit_cost,
                    narration: 'Perte de cheptel — '.($reason ?? $batch->species),
                    actor: $actor,
                ));
            }

            return $batch;
        });
    }
}
