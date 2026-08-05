<?php

namespace App\Livewire\Cooperative;

use App\Models\Contact;
use App\Models\CooperativeMember;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The member list and its create/edit form. Contribution recording is its
 * own component (RecordContribution) — a distinct lifecycle action with its
 * own validation shape, same reasoning as Crops' RecordHarvest.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $statusFilter = '';

    public bool $adding = false;

    public ?string $editingId = null;

    public string $contactId = '';

    public string $membershipNumber = '';

    public string $joinedOn = '';

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('cooperative.view');
    }

    public function startAdding(): void
    {
        $this->authorize('create', CooperativeMember::class);
        $this->resetForm();
        $this->adding = true;
    }

    public function edit(string $memberId): void
    {
        $member = CooperativeMember::findOrFail($memberId);
        $this->authorize('update', $member);

        $this->editingId = $member->id;
        $this->contactId = $member->contact_id;
        $this->membershipNumber = (string) $member->membership_number;
        $this->joinedOn = (string) $member->joined_on?->toDateString();
        $this->notes = (string) $member->notes;
        $this->adding = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'contactId' => ['required_without:editingId', 'exists:contacts,id'],
            'membershipNumber' => ['nullable', 'string', 'max:100'],
            'joinedOn' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($this->editingId) {
            $member = CooperativeMember::findOrFail($this->editingId);
            $this->authorize('update', $member);
            $member->update([
                'membership_number' => $data['membershipNumber'] ?: null,
                'joined_on' => $data['joinedOn'] ?: null,
                'notes' => $data['notes'] ?: null,
            ]);
        } else {
            $this->authorize('create', CooperativeMember::class);
            CooperativeMember::create([
                'contact_id' => $data['contactId'],
                'membership_number' => $data['membershipNumber'] ?: null,
                'joined_on' => $data['joinedOn'] ?: null,
                'notes' => $data['notes'] ?: null,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);
        }

        $this->resetForm();
        $this->adding = false;
    }

    public function delete(string $memberId): void
    {
        $member = CooperativeMember::findOrFail($memberId);
        $this->authorize('delete', $member);
        $member->delete();
    }

    /** No-op body — Livewire re-renders the list on any listened event. */
    #[On('contribution-recorded')]
    public function refreshAfterContribution(): void {}

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'contactId', 'membershipNumber', 'joinedOn', 'notes']);
    }

    public function render(): View
    {
        $members = CooperativeMember::query()
            ->with('contact')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.cooperative.index', [
            'members' => $members,
            'contacts' => Contact::query()->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Cooperative', 'active' => 'cooperative']);
    }
}
