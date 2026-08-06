<?php

namespace App\Services\Agri;

use App\Models\CropCycle;
use RuntimeException;

/**
 * Status transitions short of harvest. Harvesting itself goes through
 * HarvestRecorder, since it also has to write stock — this only moves a
 * cycle between the statuses that don't touch anything outside the row.
 */
class CropCyclePlanner
{
    public function markPlanted(CropCycle $cycle, ?string $plantedOn = null): CropCycle
    {
        $this->guardNotFinished($cycle);

        $cycle->forceFill([
            'status' => 'planted',
            'actual_planting_date' => $plantedOn ?? now()->toDateString(),
        ])->save();

        return $cycle;
    }

    public function markGrowing(CropCycle $cycle, ?string $growthStage = null): CropCycle
    {
        $this->guardNotFinished($cycle);

        $cycle->forceFill([
            'status' => 'growing',
            'growth_stage' => $growthStage ?? $cycle->growth_stage,
        ])->save();

        return $cycle;
    }

    /** Closes a cycle without a harvest — the planting failed, or was abandoned. */
    public function close(CropCycle $cycle): CropCycle
    {
        if ($cycle->status === 'closed') {
            throw new RuntimeException('This crop cycle is already closed.');
        }

        $cycle->forceFill(['status' => 'closed'])->save();

        return $cycle;
    }

    /**
     * Public so the plain update endpoint (CropCycleController::update()) can
     * reuse the same guard before touching status/stage fields directly —
     * a harvested or closed cycle must not be reopened through any path.
     */
    public function guardNotFinished(CropCycle $cycle): void
    {
        if (in_array($cycle->status, ['harvested', 'closed'], true)) {
            throw new RuntimeException("This crop cycle is {$cycle->status} and cannot change stage.");
        }
    }
}
