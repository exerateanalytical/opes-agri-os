<?php

namespace App\Livewire\Livestock;

use App\Models\Animal;
use App\Models\Farm;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The animal list and its create/edit form. Health and production recording
 * are their own components — distinct lifecycle actions with their own
 * validation shapes, not field edits, same reasoning as Crops' RecordHarvest.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $statusFilter = '';

    public bool $adding = false;

    public ?string $editingId = null;

    public string $farmId = '';

    public string $tagNumber = '';

    public string $species = '';

    public string $breed = '';

    public string $sex = '';

    public string $dateOfBirth = '';

    public string $notes = '';

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
            'dateOfBirth' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $attributes = [
            'farm_id' => $data['farmId'] ?: null,
            'tag_number' => $data['tagNumber'] ?: null,
            'species' => $data['species'],
            'breed' => $data['breed'] ?: null,
            'sex' => $data['sex'] ?: null,
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

    /** No-op body — Livewire re-renders the list on any listened event. */
    #[On('health-recorded')]
    #[On('production-recorded')]
    public function refreshAfterRecord(): void {}

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'farmId', 'tagNumber', 'species', 'breed', 'sex', 'dateOfBirth', 'notes']);
    }

    public function render(): View
    {
        $animals = Animal::query()
            ->with('farm')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.livestock.index', [
            'animals' => $animals,
            'farms' => Farm::query()->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Livestock', 'active' => 'livestock']);
    }
}
