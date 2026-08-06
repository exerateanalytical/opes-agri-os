<?php

namespace App\Domain\Utilities\Support;

use App\Models\UtilityAccount;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Cross-checks a new reading against the account's most recent one, shared
 * between the API request and the Livewire form so the two can't drift.
 *
 * Two things are enforced: readings must be entered in chronological order
 * (no backdating over a reading already on file — this domain has no
 * "correction" concept the way AnimalBatchAdjustment does, so out-of-order
 * entry is simply rejected), and the meter reading must not fall below the
 * previous one unless `meter_reset` says the meter itself was swapped or
 * zeroed, per the migration note on `utility_readings`.
 */
class ReadingConsistency
{
    public static function check(
        UtilityAccount $account,
        string $readOn,
        ?float $meterReading,
        bool $meterReset,
    ): void {
        $previous = $account->readings()
            ->orderByDesc('read_on')
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return;
        }

        if (Carbon::parse($readOn)->lt($previous->read_on)) {
            throw ValidationException::withMessages([
                'read_on' => "Readings must be entered in order — the most recent reading on file is dated {$previous->read_on->toDateString()}.",
            ]);
        }

        if ($meterReading !== null && $previous->meter_reading !== null && ! $meterReset) {
            if ($meterReading < (float) $previous->meter_reading) {
                throw ValidationException::withMessages([
                    'meter_reading' => 'Meter reading cannot be lower than the previous reading ('.$previous->meter_reading.') unless the meter was reset.',
                ]);
            }
        }
    }
}
