<?php

namespace App\Livewire\Partners;

use App\Models\Partner;
use App\Models\PartnerInteraction;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

class RecordInteraction extends Component
{
    use AuthorizesRequests;

    public ?string $partnerId = null;

    public string $interactionDate = '';

    public string $type = 'meeting';

    public string $summary = '';

    #[On('open-interaction')]
    public function open(string $partnerId): void
    {
        $partner = Partner::findOrFail($partnerId);
        $this->authorize('recordInteraction', $partner);

        $this->partnerId = $partner->id;
        $this->interactionDate = now()->toDateString();
        $this->type = 'meeting';
        $this->summary = '';
    }

    public function close(): void
    {
        $this->partnerId = null;
    }

    public function save(): void
    {
        $data = $this->validate([
            'interactionDate' => ['required', 'date'],
            'type' => ['required', 'in:'.implode(',', PartnerInteraction::TYPES)],
            'summary' => ['required', 'string'],
        ]);

        $partner = Partner::findOrFail($this->partnerId);
        $this->authorize('recordInteraction', $partner);

        $partner->interactions()->create([
            'interaction_date' => $data['interactionDate'],
            'type' => $data['type'],
            'summary' => $data['summary'],
            'recorded_by' => auth()->id(),
        ]);

        $this->partnerId = null;
        $this->dispatch('interaction-recorded');
    }

    public function render(): View
    {
        return view('livewire.partners.record-interaction', [
            'partner' => $this->partnerId ? Partner::find($this->partnerId) : null,
        ]);
    }
}
