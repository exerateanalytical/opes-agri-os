<?php

namespace App\Livewire\Farms;

use App\Models\Farm;
use App\Models\Field;
use App\Models\Season;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Farms, fields and seasons in one module page — following the Assets
 * `Index.php` precedent: CRUD-shaped, low lifecycle complexity, so splitting
 * into per-entity components would be premature. The field boundary is a
 * simple list of lat/lng point rows rather than an interactive map (no
 * mapping library is part of this build) — see
 * docs/architecture/agri-platform-roadmap.md.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $tab = 'farms'; // farms|fields|seasons

    // ── Farm form ───────────────────────────────────────────────────────
    public bool $addingFarm = false;

    public ?string $editingFarmId = null;

    public string $farmName = '';

    public string $farmLocation = '';

    public string $farmSize = '';

    public string $farmOwnership = 'owned';

    public string $farmNotes = '';

    // ── Field form ──────────────────────────────────────────────────────
    public bool $addingField = false;

    public ?string $editingFieldId = null;

    public string $fieldFarmId = '';

    public string $fieldName = '';

    public string $fieldArea = '';

    public string $fieldOwnership = 'owned';

    public string $fieldLeaseStart = '';

    public string $fieldLeaseEnd = '';

    /** @var array<int, array{lat: string, lng: string}> */
    public array $fieldBoundary = [];

    // ── Season form ─────────────────────────────────────────────────────
    public bool $addingSeason = false;

    public ?string $editingSeasonId = null;

    public string $seasonName = '';

    public string $seasonStartsOn = '';

    public string $seasonEndsOn = '';

    public function mount(): void
    {
        Gate::authorize('farms.view');
    }

    public function startAddingFarm(): void
    {
        $this->authorize('create', Farm::class);
        $this->resetFarmForm();
        $this->addingFarm = true;
    }

    public function editFarm(string $farmId): void
    {
        $farm = Farm::findOrFail($farmId);
        $this->authorize('update', $farm);

        $this->editingFarmId = $farm->id;
        $this->farmName = $farm->name;
        $this->farmLocation = (string) $farm->location_text;
        $this->farmSize = (string) $farm->size_hectares;
        $this->farmOwnership = $farm->ownership_type;
        $this->farmNotes = (string) $farm->notes;
        $this->addingFarm = true;
    }

    public function saveFarm(): void
    {
        $data = $this->validate([
            'farmName' => ['required', 'string', 'max:255'],
            'farmLocation' => ['nullable', 'string', 'max:255'],
            'farmSize' => ['nullable', 'numeric', 'min:0'],
            'farmOwnership' => ['required', 'in:owned,leased,mixed'],
            'farmNotes' => ['nullable', 'string'],
        ]);

        $attributes = [
            'name' => $data['farmName'],
            'location_text' => $data['farmLocation'] ?: null,
            'size_hectares' => $data['farmSize'] !== '' ? $data['farmSize'] : null,
            'ownership_type' => $data['farmOwnership'],
            'notes' => $data['farmNotes'] ?: null,
        ];

        if ($this->editingFarmId) {
            $farm = Farm::findOrFail($this->editingFarmId);
            $this->authorize('update', $farm);
            $farm->update($attributes);
        } else {
            $this->authorize('create', Farm::class);
            Farm::create($attributes + ['created_by' => auth()->id()]);
        }

        $this->resetFarmForm();
        $this->addingFarm = false;
    }

    public function deleteFarm(string $farmId): void
    {
        $farm = Farm::findOrFail($farmId);
        $this->authorize('delete', $farm);
        $farm->delete();
    }

    protected function resetFarmForm(): void
    {
        $this->reset(['editingFarmId', 'farmName', 'farmLocation', 'farmSize', 'farmNotes']);
        $this->farmOwnership = 'owned';
    }

    public function startAddingField(): void
    {
        $this->authorize('create', Field::class);
        $this->resetFieldForm();
        $this->addingField = true;
    }

    public function editField(string $fieldId): void
    {
        $field = Field::findOrFail($fieldId);
        $this->authorize('update', $field);

        $this->editingFieldId = $field->id;
        $this->fieldFarmId = $field->farm_id;
        $this->fieldName = $field->name;
        $this->fieldArea = (string) $field->area_hectares;
        $this->fieldOwnership = $field->ownership_type;
        $this->fieldLeaseStart = (string) $field->lease_start?->toDateString();
        $this->fieldLeaseEnd = (string) $field->lease_end?->toDateString();
        $this->fieldBoundary = collect($field->boundary ?? [])
            ->map(fn ($point) => ['lat' => (string) $point['lat'], 'lng' => (string) $point['lng']])
            ->all();
        $this->addingField = true;
    }

    public function addBoundaryPoint(): void
    {
        $this->fieldBoundary[] = ['lat' => '', 'lng' => ''];
    }

    public function removeBoundaryPoint(int $index): void
    {
        unset($this->fieldBoundary[$index]);
        $this->fieldBoundary = array_values($this->fieldBoundary);
    }

    public function saveField(): void
    {
        $data = $this->validate([
            'fieldFarmId' => ['required', 'exists:farms,id'],
            'fieldName' => ['required', 'string', 'max:255'],
            'fieldArea' => ['nullable', 'numeric', 'min:0'],
            'fieldOwnership' => ['required', 'in:owned,leased'],
            'fieldLeaseStart' => ['nullable', 'date'],
            'fieldLeaseEnd' => ['nullable', 'date', 'after_or_equal:fieldLeaseStart'],
            'fieldBoundary.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'fieldBoundary.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $boundary = collect($this->fieldBoundary)
            ->filter(fn ($p) => $p['lat'] !== '' && $p['lng'] !== '')
            ->map(fn ($p) => ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']])
            ->values()
            ->all();

        $attributes = [
            'farm_id' => $data['fieldFarmId'],
            'name' => $data['fieldName'],
            'area_hectares' => $data['fieldArea'] !== '' ? $data['fieldArea'] : null,
            'boundary' => $boundary ?: null,
            'ownership_type' => $data['fieldOwnership'],
            'lease_start' => $data['fieldLeaseStart'] ?: null,
            'lease_end' => $data['fieldLeaseEnd'] ?: null,
        ];

        if ($this->editingFieldId) {
            $field = Field::findOrFail($this->editingFieldId);
            $this->authorize('update', $field);
            $field->update($attributes);
        } else {
            $this->authorize('create', Field::class);
            Field::create($attributes);
        }

        $this->resetFieldForm();
        $this->addingField = false;
    }

    public function deleteField(string $fieldId): void
    {
        $field = Field::findOrFail($fieldId);
        $this->authorize('delete', $field);
        $field->delete();
    }

    protected function resetFieldForm(): void
    {
        $this->reset([
            'editingFieldId', 'fieldFarmId', 'fieldName', 'fieldArea',
            'fieldLeaseStart', 'fieldLeaseEnd', 'fieldBoundary',
        ]);
        $this->fieldOwnership = 'owned';
    }

    public function startAddingSeason(): void
    {
        $this->authorize('create', Season::class);
        $this->resetSeasonForm();
        $this->addingSeason = true;
    }

    public function editSeason(string $seasonId): void
    {
        $season = Season::findOrFail($seasonId);
        $this->authorize('update', $season);

        $this->editingSeasonId = $season->id;
        $this->seasonName = $season->name;
        $this->seasonStartsOn = $season->starts_on->toDateString();
        $this->seasonEndsOn = (string) $season->ends_on?->toDateString();
        $this->addingSeason = true;
    }

    public function saveSeason(): void
    {
        $data = $this->validate([
            'seasonName' => ['required', 'string', 'max:255'],
            'seasonStartsOn' => ['required', 'date'],
            'seasonEndsOn' => ['nullable', 'date', 'after_or_equal:seasonStartsOn'],
        ]);

        $attributes = [
            'name' => $data['seasonName'],
            'starts_on' => $data['seasonStartsOn'],
            'ends_on' => $data['seasonEndsOn'] ?: null,
        ];

        if ($this->editingSeasonId) {
            $season = Season::findOrFail($this->editingSeasonId);
            $this->authorize('update', $season);
            $season->update($attributes);
        } else {
            $this->authorize('create', Season::class);
            Season::create($attributes);
        }

        $this->resetSeasonForm();
        $this->addingSeason = false;
    }

    public function deleteSeason(string $seasonId): void
    {
        $season = Season::findOrFail($seasonId);
        $this->authorize('delete', $season);
        $season->delete();
    }

    protected function resetSeasonForm(): void
    {
        $this->reset(['editingSeasonId', 'seasonName', 'seasonStartsOn', 'seasonEndsOn']);
    }

    public function render(): View
    {
        return view('livewire.farms.index', [
            'farms' => Farm::query()->withCount('fields')->orderBy('name')->get(),
            'fields' => Field::query()->with('farm')->orderBy('name')->get(),
            'seasons' => Season::query()->orderByDesc('starts_on')->get(),
        ])->layout('components.layouts.app', ['title' => 'Farms', 'active' => 'farms']);
    }
}
