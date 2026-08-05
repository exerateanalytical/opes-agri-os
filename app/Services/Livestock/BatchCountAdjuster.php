<?php

namespace App\Services\Livestock;

use App\Models\AnimalBatch;
use RuntimeException;

/**
 * Adjusts a flock's running count — mortality, culling, sales, hatching —
 * without ever letting it go negative or above what was ever acquired plus
 * whatever's been added since. Kept deliberately simple: no ledger of
 * individual adjustment rows in V2 M2, just the running total.
 */
class BatchCountAdjuster
{
    public function adjust(AnimalBatch $batch, int $change): AnimalBatch
    {
        $newCount = $batch->current_count + $change;

        if ($newCount < 0) {
            throw new RuntimeException('This would take the batch below zero.');
        }

        $batch->forceFill(['current_count' => $newCount])->save();

        return $batch;
    }
}
