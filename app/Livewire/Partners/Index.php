<?php

namespace App\Livewire\Partners;

use App\Models\Partner;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The partner list and its create/edit form. Logging an interaction is its
 * own component (RecordInteraction) — a distinct lifecycle action with its
 * own validation shape, same reasoning as Utilities' RecordReading.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $typeFilter = '';

    public bool $adding = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $type = 'ngo';

    public string $contactPerson = '';

    public string $email = '';

    public string $phone = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Partner::class);
    }

    public function startAdding(): void
    {
        $this->authorize('create', Partner::class);
        $this->resetForm();
        $this->adding = true;
    }

    public function edit(string $partnerId): void
    {
        $partner = Partner::findOrFail($partnerId);
        $this->authorize('update', $partner);

        $this->editingId = $partner->id;
        $this->name = $partner->name;
        $this->type = $partner->type;
        $this->contactPerson = (string) $partner->contact_person;
        $this->email = (string) $partner->email;
        $this->phone = (string) $partner->phone;
        $this->notes = (string) $partner->notes;
        $this->adding = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:'.implode(',', Partner::TYPES)],
            'contactPerson' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'type' => $data['type'],
            'contact_person' => $data['contactPerson'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->editingId) {
            $partner = Partner::findOrFail($this->editingId);
            $this->authorize('update', $partner);
            $partner->update($attributes);
        } else {
            $this->authorize('create', Partner::class);
            Partner::create($attributes + ['status' => 'active', 'created_by' => auth()->id()]);
        }

        $this->resetForm();
        $this->adding = false;
    }

    public function delete(string $partnerId): void
    {
        $partner = Partner::findOrFail($partnerId);
        $this->authorize('delete', $partner);
        $partner->delete();
    }

    /** No-op body — Livewire re-renders the list on any listened event. */
    #[On('interaction-recorded')]
    public function refreshAfterInteraction(): void {}

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'contactPerson', 'email', 'phone', 'notes']);
        $this->type = 'ngo';
    }

    public function render(): View
    {
        $partners = Partner::query()
            ->with(['interactions' => fn ($q) => $q->orderByDesc('interaction_date')->limit(1)])
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.partners.index', [
            'partners' => $partners,
        ])->layout('components.layouts.app', ['title' => 'Partners', 'active' => 'partner-crm']);
    }
}
