<?php

namespace App\Livewire\Livestock;

use App\Models\Animal;
use App\Models\Item;
use App\Services\Livestock\ProductionRecorder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

class RecordProduction extends Component
{
    use AuthorizesRequests;

    public ?string $animalId = null;

    public string $itemId = '';

    public string $quantity = '';

    public string $unitCost = '';

    public string $recordedOn = '';

    public string $notes = '';

    #[On('open-production')]
    public function open(string $animalId): void
    {
        $animal = Animal::findOrFail($animalId);
        $this->authorize('recordProduction', $animal);

        $this->animalId = $animal->id;
        $this->itemId = '';
        $this->quantity = '';
        $this->unitCost = '';
        $this->recordedOn = now()->toDateString();
        $this->notes = '';
    }

    public function close(): void
    {
        $this->animalId = null;
    }

    public function save(ProductionRecorder $recorder): void
    {
        $data = $this->validate([
            'itemId' => ['nullable', 'exists:items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'recordedOn' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $animal = Animal::findOrFail($this->animalId);
        $this->authorize('recordProduction', $animal);

        try {
            $recorder->record(
                $animal,
                auth()->user(),
                $data['itemId'] ? Item::find($data['itemId']) : null,
                (float) $data['quantity'],
                $data['recordedOn'] ?: null,
                [
                    'notes' => $data['notes'] ?: null,
                    'unit_cost' => $data['unitCost'] !== '' ? (float) $data['unitCost'] : null,
                ],
            );
        } catch (RuntimeException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->animalId = null;
        $this->dispatch('production-recorded');
    }

    public function render(): View
    {
        return view('livewire.livestock.record-production', [
            'animal' => $this->animalId ? Animal::find($this->animalId) : null,
            'items' => Item::query()->products()->orderBy('name')->get(),
        ]);
    }
}
