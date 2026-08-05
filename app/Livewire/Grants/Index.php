<?php

namespace App\Livewire\Grants;

use App\Models\GrantProject;
use App\Models\Partner;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The grant project list and its create/edit form. Recording a receipt or
 * an expenditure is its own component (RecordTransaction) — a distinct
 * lifecycle action with its own validation shape and ledger posting, same
 * reasoning as Cooperative's RecordContribution/loan actions.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $statusFilter = '';

    public bool $adding = false;

    public ?string $editingId = null;

    public string $partnerId = '';

    public string $name = '';

    public string $description = '';

    public string $totalAmount = '';

    public string $currency = 'USD';

    public string $startDate = '';

    public string $endDate = '';

    public function mount(): void
    {
        $this->authorize('viewAny', GrantProject::class);
    }

    public function startAdding(): void
    {
        $this->authorize('create', GrantProject::class);
        $this->resetForm();
        $this->adding = true;
    }

    public function edit(string $projectId): void
    {
        $project = GrantProject::findOrFail($projectId);
        $this->authorize('update', $project);

        $this->editingId = $project->id;
        $this->partnerId = (string) $project->partner_id;
        $this->name = $project->name;
        $this->description = (string) $project->description;
        $this->totalAmount = (string) $project->total_amount;
        $this->currency = $project->currency;
        $this->startDate = (string) $project->start_date?->toDateString();
        $this->endDate = (string) $project->end_date?->toDateString();
        $this->adding = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'partnerId' => ['nullable', 'exists:partners,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'totalAmount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ]);

        $attributes = [
            'partner_id' => $data['partnerId'] ?: null,
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'total_amount' => $data['totalAmount'],
            'currency' => strtoupper($data['currency']),
            'start_date' => $data['startDate'] ?: null,
            'end_date' => $data['endDate'] ?: null,
        ];

        if ($this->editingId) {
            $project = GrantProject::findOrFail($this->editingId);
            $this->authorize('update', $project);
            $project->update($attributes);
        } else {
            $this->authorize('create', GrantProject::class);
            GrantProject::create($attributes + ['status' => 'planned', 'created_by' => auth()->id()]);
        }

        $this->resetForm();
        $this->adding = false;
    }

    public function delete(string $projectId): void
    {
        $project = GrantProject::findOrFail($projectId);
        $this->authorize('delete', $project);
        $project->delete();
    }

    /** No-op body — Livewire re-renders the list on any listened event. */
    #[On('grant-transaction-recorded')]
    public function refreshAfterTransaction(): void {}

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'partnerId', 'description', 'totalAmount', 'startDate', 'endDate']);
        $this->currency = 'USD';
    }

    public function render(): View
    {
        $projects = GrantProject::query()
            ->with('partner')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.grants.index', [
            'projects' => $projects,
            'partners' => Partner::query()->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Grants', 'active' => 'grants']);
    }
}
