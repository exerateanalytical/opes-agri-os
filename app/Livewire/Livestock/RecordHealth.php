<?php

namespace App\Livewire\Livestock;

use App\Models\Animal;
use App\Models\AnimalHealthRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

class RecordHealth extends Component
{
    use AuthorizesRequests;

    public ?string $animalId = null;

    public string $recordType = 'vaccination';

    public string $description = '';

    public string $administeredOn = '';

    public string $nextDueOn = '';

    public string $cost = '';

    public string $notes = '';

    #[On('open-health')]
    public function open(string $animalId): void
    {
        $animal = Animal::findOrFail($animalId);
        $this->authorize('recordHealth', $animal);

        $this->animalId = $animal->id;
        $this->recordType = 'vaccination';
        $this->description = '';
        $this->administeredOn = now()->toDateString();
        $this->nextDueOn = '';
        $this->cost = '';
        $this->notes = '';
    }

    public function close(): void
    {
        $this->animalId = null;
    }

    public function save(): void
    {
        $data = $this->validate([
            'recordType' => ['required', 'in:'.implode(',', AnimalHealthRecord::TYPES)],
            'description' => ['required', 'string', 'max:255'],
            'administeredOn' => ['required', 'date'],
            'nextDueOn' => ['nullable', 'date', 'after_or_equal:administeredOn'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $animal = Animal::findOrFail($this->animalId);
        $this->authorize('recordHealth', $animal);

        $animal->healthRecords()->create([
            'record_type' => $data['recordType'],
            'description' => $data['description'],
            'administered_on' => $data['administeredOn'],
            'next_due_on' => $data['nextDueOn'] ?: null,
            'cost' => $data['cost'] !== '' ? $data['cost'] : null,
            'notes' => $data['notes'] ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->animalId = null;
        $this->dispatch('health-recorded');
    }

    public function render(): View
    {
        return view('livewire.livestock.record-health', [
            'animal' => $this->animalId ? Animal::find($this->animalId) : null,
            'types' => AnimalHealthRecord::TYPES,
        ]);
    }
}
