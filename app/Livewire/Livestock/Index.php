<?php

namespace App\Livewire\Livestock;

use App\Models\Animal;
use App\Models\AnimalBatch;
use App\Models\Farm;
use App\Services\Livestock\BatchCountAdjuster;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Individually-tracked animals and flock-style batches in one module page —
 * following the Farms `Index.php` precedent (tabbed, CRUD-shaped). Health
 * and production recording are their own components — distinct lifecycle
 * actions with their own validation shapes, same reasoning as Crops'
 * RecordHarvest.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $tab = 'animals'; // animals|batches

    #[Url]
    public string $statusFilter = '';

    // ── Animal form ─────────────────────────────────────────────────────
    public bool $adding = false;

    public ?string $editingId = null;

    public string $farmId = '';

    public string $tagNumber = '';

    public string $species = '';

    public string $breed = '';

    public string $sex = '';

    public string $sireId = '';

    public string $damId = '';

    public string $dateOfBirth = '';

    public string $notes = '';

    // ── Batch form ──────────────────────────────────────────────────────
    public bool $addingBatch = false;

    public ?string $editingBatchId = null;

    public string $batchFarmId = '';

    public string $batchSpecies = '';

    public string $batchBreed = '';

    public string $batchInitialCount = '';

    public string $batchAcquiredOn = '';

    public string $batchNotes = '';

    public function mount(): void
    {
        Gate::authorize('livestock.view');
    }

    public function startAdding(): void
    {
        $this->authorize('create', Animal::class);
        $this->resetForm();
        $this->adding = true;
    }

    public function edit(string $animalId): void
    {
        $animal = Animal::findOrFail($animalId);
        $this->authorize('update', $animal);

        $this->editingId = $animal->id;
        $this->farmId = (string) $animal->farm_id;
        $this->tagNumber = (string) $animal->tag_number;
        $this->species = $animal->species;
        $this->breed = (string) $animal->breed;
        $this->sex = (string) $animal->sex;
        $this->sireId = (string) $animal->sire_id;
        $this->damId = (string) $animal->dam_id;
        $this->dateOfBirth = (string) $animal->date_of_birth?->toDateString();
        $this->notes = (string) $animal->notes;
        $this->adding = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'farmId' => ['nullable', 'exists:farms,id'],
            'tagNumber' => ['nullable', 'string', 'max:100'],
            'species' => ['required', 'string', 'max:100'],
            'breed' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', 'in:male,female'],
            'sireId' => ['nullable', 'exists:animals,id'],
            'damId' => ['nullable', 'exists:animals,id'],
            'dateOfBirth' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $attributes = [
            'farm_id' => $data['farmId'] ?: null,
            'tag_number' => $data['tagNumber'] ?: null,
            'species' => $data['species'],
            'breed' => $data['breed'] ?: null,
            'sex' => $data['sex'] ?: null,
            'sire_id' => $data['sireId'] ?: null,
            'dam_id' => $data['damId'] ?: null,
            'date_of_birth' => $data['dateOfBirth'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->editingId) {
            $animal = Animal::findOrFail($this->editingId);
            $this->authorize('update', $animal);
            $animal->update($attributes);
        } else {
            $this->authorize('create', Animal::class);
            Animal::create($attributes + ['status' => 'active', 'created_by' => auth()->id()]);
        }

        $this->resetForm();
        $this->adding = false;
    }

    public function delete(string $animalId): void
    {
        $animal = Animal::findOrFail($animalId);
        $this->authorize('delete', $animal);
        $animal->delete();
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'farmId', 'tagNumber', 'species', 'breed', 'sex',
            'sireId', 'damId', 'dateOfBirth', 'notes',
        ]);
    }

    public function startAddingBatch(): void
    {
        $this->authorize('create', AnimalBatch::class);
        $this->resetBatchForm();
        $this->addingBatch = true;
    }

    public function editBatch(string $batchId): void
    {
        $batch = AnimalBatch::findOrFail($batchId);
        $this->authorize('update', $batch);

        $this->editingBatchId = $batch->id;
        $this->batchFarmId = (string) $batch->farm_id;
        $this->batchSpecies = $batch->species;
        $this->batchBreed = (string) $batch->breed;
        $this->batchInitialCount = (string) $batch->initial_count;
        $this->batchAcquiredOn = (string) $batch->acquired_on?->toDateString();
        $this->batchNotes = (string) $batch->notes;
        $this->addingBatch = true;
    }

    public function saveBatch(): void
    {
        $data = $this->validate([
            'batchFarmId' => ['nullable', 'exists:farms,id'],
            'batchSpecies' => ['required', 'string', 'max:100'],
            'batchBreed' => ['nullable', 'string', 'max:100'],
            'batchInitialCount' => ['required', 'integer', 'min:1'],
            'batchAcquiredOn' => ['nullable', 'date'],
            'batchNotes' => ['nullable', 'string'],
        ]);

        if ($this->editingBatchId) {
            $batch = AnimalBatch::findOrFail($this->editingBatchId);
            $this->authorize('update', $batch);
            $batch->update([
                'farm_id' => $data['batchFarmId'] ?: null,
                'species' => $data['batchSpecies'],
                'breed' => $data['batchBreed'] ?: null,
                'acquired_on' => $data['batchAcquiredOn'] ?: null,
                'notes' => $data['batchNotes'] ?: null,
            ]);
        } else {
            $this->authorize('create', AnimalBatch::class);
            AnimalBatch::create([
                'farm_id' => $data['batchFarmId'] ?: null,
                'species' => $data['batchSpecies'],
                'breed' => $data['batchBreed'] ?: null,
                'initial_count' => $data['batchInitialCount'],
                'current_count' => $data['batchInitialCount'],
                'acquired_on' => $data['batchAcquiredOn'] ?: null,
                'notes' => $data['batchNotes'] ?: null,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);
        }

        $this->resetBatchForm();
        $this->addingBatch = false;
    }

    public function adjustBatchCount(string $batchId, int $change, BatchCountAdjuster $adjuster): void
    {
        $batch = AnimalBatch::findOrFail($batchId);
        $this->authorize('update', $batch);

        try {
            $adjuster->adjust($batch, $change);
        } catch (RuntimeException $e) {
            $this->addError('batch', $e->getMessage());
        }
    }

    public function deleteBatch(string $batchId): void
    {
        $batch = AnimalBatch::findOrFail($batchId);
        $this->authorize('delete', $batch);
        $batch->delete();
    }

    protected function resetBatchForm(): void
    {
        $this->reset([
            'editingBatchId', 'batchFarmId', 'batchSpecies', 'batchBreed',
            'batchInitialCount', 'batchAcquiredOn', 'batchNotes',
        ]);
    }

    /** No-op body — Livewire re-renders the list on any listened event. */
    #[On('health-recorded')]
    #[On('production-recorded')]
    public function refreshAfterRecord(): void {}

    public function render(): View
    {
        $animals = Animal::query()
            ->with('farm')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();

        $batches = AnimalBatch::query()
            ->with('farm')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.livestock.index', [
            'animals' => $animals,
            'batches' => $batches,
            'farms' => Farm::query()->orderBy('name')->get(),
            'males' => Animal::query()->where('sex', 'male')->orderBy('tag_number')->get(),
            'females' => Animal::query()->where('sex', 'female')->orderBy('tag_number')->get(),
        ])->layout('components.layouts.app', ['title' => 'Livestock', 'active' => 'livestock']);
    }
}
