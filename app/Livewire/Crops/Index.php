<?php

namespace App\Livewire\Crops;

use App\Models\CropCycle;
use App\Models\Field;
use App\Models\Item;
use App\Models\Season;
use App\Services\Agri\CropCyclePlanner;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The crop cycle list and its create/edit form. Harvest recording is its
 * own component (RecordHarvest) — a distinct lifecycle action with its own
 * validation shape (quantity, batch, cost), not a field edit.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $statusFilter = '';

    public bool $adding = false;

    public ?string $editingId = null;

    public string $fieldId = '';

    public string $seasonId = '';

    public string $itemId = '';

    public string $plannedPlantingDate = '';

    public string $plannedHarvestDate = '';

    public string $plannedYieldQty = '';

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('crops.view');
    }

    public function startAdding(): void
    {
        $this->authorize('create', CropCycle::class);
        $this->resetForm();
        $this->adding = true;
    }

    public function edit(string $cropCycleId): void
    {
        $cycle = CropCycle::findOrFail($cropCycleId);
        $this->authorize('update', $cycle);

        $this->editingId = $cycle->id;
        $this->fieldId = $cycle->field_id;
        $this->seasonId = $cycle->season_id;
        $this->itemId = (string) $cycle->item_id;
        $this->plannedPlantingDate = (string) $cycle->planned_planting_date?->toDateString();
        $this->plannedHarvestDate = (string) $cycle->planned_harvest_date?->toDateString();
        $this->plannedYieldQty = (string) $cycle->planned_yield_qty;
        $this->notes = (string) $cycle->notes;
        $this->adding = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'fieldId' => ['required', 'exists:fields,id'],
            'seasonId' => ['required', 'exists:seasons,id'],
            'itemId' => ['nullable', 'exists:items,id'],
            'plannedPlantingDate' => ['nullable', 'date'],
            'plannedHarvestDate' => ['nullable', 'date', 'after_or_equal:plannedPlantingDate'],
            'plannedYieldQty' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $attributes = [
            'field_id' => $data['fieldId'],
            'season_id' => $data['seasonId'],
            'item_id' => $data['itemId'] ?: null,
            'planned_planting_date' => $data['plannedPlantingDate'] ?: null,
            'planned_harvest_date' => $data['plannedHarvestDate'] ?: null,
            'planned_yield_qty' => $data['plannedYieldQty'] !== '' ? $data['plannedYieldQty'] : null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->editingId) {
            $cycle = CropCycle::findOrFail($this->editingId);
            $this->authorize('update', $cycle);
            $cycle->update($attributes);
        } else {
            $this->authorize('create', CropCycle::class);
            CropCycle::create($attributes + ['status' => 'planned', 'created_by' => auth()->id()]);
        }

        $this->resetForm();
        $this->adding = false;
    }

    public function markPlanted(string $cropCycleId, CropCyclePlanner $planner): void
    {
        $cycle = CropCycle::findOrFail($cropCycleId);
        $this->authorize('update', $cycle);
        $planner->markPlanted($cycle);
    }

    public function markGrowing(string $cropCycleId, CropCyclePlanner $planner): void
    {
        $cycle = CropCycle::findOrFail($cropCycleId);
        $this->authorize('update', $cycle);
        $planner->markGrowing($cycle);
    }

    public function close(string $cropCycleId, CropCyclePlanner $planner): void
    {
        $cycle = CropCycle::findOrFail($cropCycleId);
        $this->authorize('update', $cycle);
        $planner->close($cycle);
    }

    /** No-op body — Livewire re-renders the list on any listened event. */
    #[On('harvest-recorded')]
    public function refreshAfterHarvest(): void {}

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'fieldId', 'seasonId', 'itemId',
            'plannedPlantingDate', 'plannedHarvestDate', 'plannedYieldQty', 'notes',
        ]);
    }

    public function render(): View
    {
        $cycles = CropCycle::query()
            ->with(['field.farm', 'season', 'item'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.crops.index', [
            'cycles' => $cycles,
            'fields' => Field::query()->orderBy('name')->get(),
            'seasons' => Season::query()->orderByDesc('starts_on')->get(),
            'items' => Item::query()->products()->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Crops', 'active' => 'crops']);
    }
}
