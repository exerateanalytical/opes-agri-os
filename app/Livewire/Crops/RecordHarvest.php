<?php

namespace App\Livewire\Crops;

use App\Models\CropCycle;
use App\Services\Agri\HarvestRecorder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * A dedicated modal for the one action that isn't a field edit: harvesting
 * writes stock, so it needs the same care as any other stock-arriving
 * screen — see App\Livewire\Products\Form's delivery form for the sibling
 * pattern.
 */
class RecordHarvest extends Component
{
    use AuthorizesRequests;

    public ?string $cropCycleId = null;

    public string $quantity = '';

    public string $unitCost = '';

    public string $batchNumber = '';

    public string $expiresOn = '';

    #[On('open-harvest')]
    public function open(string $cropCycleId): void
    {
        $cycle = CropCycle::findOrFail($cropCycleId);
        $this->authorize('recordHarvest', $cycle);

        $this->cropCycleId = $cycle->id;
        $this->quantity = '';
        $this->unitCost = '';
        $this->batchNumber = '';
        $this->expiresOn = '';
    }

    public function close(): void
    {
        $this->cropCycleId = null;
    }

    public function save(HarvestRecorder $recorder): void
    {
        $data = $this->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'batchNumber' => ['nullable', 'string', 'max:100'],
            'expiresOn' => ['nullable', 'date'],
        ]);

        $cycle = CropCycle::findOrFail($this->cropCycleId);
        $this->authorize('recordHarvest', $cycle);

        try {
            $recorder->record(
                $cycle,
                auth()->user(),
                (float) $data['quantity'],
                $data['unitCost'] !== '' ? (float) $data['unitCost'] : null,
                ['batch_number' => $data['batchNumber'] ?: null, 'expires_on' => $data['expiresOn'] ?: null],
            );
        } catch (RuntimeException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->cropCycleId = null;
        $this->dispatch('harvest-recorded');
    }

    public function render(): View
    {
        return view('livewire.crops.record-harvest', [
            'cropCycle' => $this->cropCycleId ? CropCycle::with('item')->find($this->cropCycleId) : null,
        ]);
    }
}
