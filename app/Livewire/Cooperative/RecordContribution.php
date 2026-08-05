<?php

namespace App\Livewire\Cooperative;

use App\Models\CooperativeMember;
use App\Models\MemberContribution;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

class RecordContribution extends Component
{
    use AuthorizesRequests;

    public ?string $memberId = null;

    public string $type = 'share_capital';

    public string $amount = '';

    public string $contributedOn = '';

    public string $reference = '';

    public string $notes = '';

    #[On('open-contribution')]
    public function open(string $memberId): void
    {
        $member = CooperativeMember::findOrFail($memberId);
        $this->authorize('recordContribution', $member);

        $this->memberId = $member->id;
        $this->type = 'share_capital';
        $this->amount = '';
        $this->contributedOn = now()->toDateString();
        $this->reference = '';
        $this->notes = '';
    }

    public function close(): void
    {
        $this->memberId = null;
    }

    public function save(): void
    {
        $data = $this->validate([
            'type' => ['required', 'in:'.implode(',', MemberContribution::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'contributedOn' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $member = CooperativeMember::findOrFail($this->memberId);
        $this->authorize('recordContribution', $member);

        $member->contributions()->create([
            'type' => $data['type'],
            'amount' => $data['amount'],
            'contributed_on' => $data['contributedOn'],
            'reference' => $data['reference'] ?: null,
            'notes' => $data['notes'] ?: null,
            'created_by' => auth()->id(),
        ]);

        $member->recomputeBalance();

        $this->memberId = null;
        $this->dispatch('contribution-recorded');
    }

    public function render(): View
    {
        return view('livewire.cooperative.record-contribution', [
            'member' => $this->memberId ? CooperativeMember::with('contact')->find($this->memberId) : null,
        ]);
    }
}
