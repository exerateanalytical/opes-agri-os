<?php

namespace App\Livewire\Utilities;

use App\Models\Farm;
use App\Models\UtilityAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The account list and its create/edit form. Reading recording is its own
 * component (RecordReading) — a distinct lifecycle action with its own
 * validation shape, same reasoning as Cooperative's RecordContribution.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $typeFilter = '';

    public bool $adding = false;

    public ?string $editingId = null;

    public string $farmId = '';

    public string $utilityType = 'electricity';

    public string $providerName = '';

    public string $accountNumber = '';

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('utilities.view');
    }

    public function startAdding(): void
    {
        $this->authorize('create', UtilityAccount::class);
        $this->resetForm();
        $this->adding = true;
    }

    public function edit(string $accountId): void
    {
        $account = UtilityAccount::findOrFail($accountId);
        $this->authorize('update', $account);

        $this->editingId = $account->id;
        $this->farmId = (string) $account->farm_id;
        $this->utilityType = $account->utility_type;
        $this->providerName = (string) $account->provider_name;
        $this->accountNumber = (string) $account->account_number;
        $this->notes = (string) $account->notes;
        $this->adding = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'farmId' => ['nullable', 'exists:farms,id'],
            'utilityType' => ['required', 'in:'.implode(',', UtilityAccount::TYPES)],
            'providerName' => ['nullable', 'string', 'max:255'],
            'accountNumber' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $attributes = [
            'farm_id' => $data['farmId'] ?: null,
            'utility_type' => $data['utilityType'],
            'provider_name' => $data['providerName'] ?: null,
            'account_number' => $data['accountNumber'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->editingId) {
            $account = UtilityAccount::findOrFail($this->editingId);
            $this->authorize('update', $account);
            $account->update($attributes);
        } else {
            $this->authorize('create', UtilityAccount::class);
            UtilityAccount::create($attributes + ['status' => 'active', 'created_by' => auth()->id()]);
        }

        $this->resetForm();
        $this->adding = false;
    }

    public function delete(string $accountId): void
    {
        $account = UtilityAccount::findOrFail($accountId);
        $this->authorize('delete', $account);
        $account->delete();
    }

    /** No-op body — Livewire re-renders the list on any listened event. */
    #[On('reading-recorded')]
    public function refreshAfterReading(): void {}

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'farmId', 'providerName', 'accountNumber', 'notes']);
        $this->utilityType = 'electricity';
    }

    public function render(): View
    {
        $accounts = UtilityAccount::query()
            ->with(['farm', 'readings' => fn ($q) => $q->orderByDesc('read_on')->limit(1)])
            ->when($this->typeFilter, fn ($q) => $q->where('utility_type', $this->typeFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.utilities.index', [
            'accounts' => $accounts,
            'farms' => Farm::query()->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Utilities', 'active' => 'utilities']);
    }
}
