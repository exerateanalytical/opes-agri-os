<?php

namespace App\Livewire\Utilities;

use App\Domain\Utilities\Support\ReadingConsistency;
use App\Models\UtilityAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

class RecordReading extends Component
{
    use AuthorizesRequests;

    public ?string $accountId = null;

    public string $readOn = '';

    public string $meterReading = '';

    public bool $meterReset = false;

    public string $consumption = '';

    public string $cost = '';

    #[On('open-reading')]
    public function open(string $accountId): void
    {
        $account = UtilityAccount::findOrFail($accountId);
        $this->authorize('recordReading', $account);

        $this->accountId = $account->id;
        $this->readOn = now()->toDateString();
        $this->meterReading = '';
        $this->meterReset = false;
        $this->consumption = '';
        $this->cost = '';
    }

    public function close(): void
    {
        $this->accountId = null;
    }

    public function save(): void
    {
        $data = $this->validate([
            'readOn' => ['required', 'date'],
            'meterReading' => ['nullable', 'numeric', 'min:0'],
            'consumption' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $account = UtilityAccount::findOrFail($this->accountId);
        $this->authorize('recordReading', $account);

        $meterReading = $data['meterReading'] !== '' ? (float) $data['meterReading'] : null;

        ReadingConsistency::check(
            account: $account,
            readOn: $data['readOn'],
            meterReading: $meterReading,
            meterReset: $this->meterReset,
        );

        $account->readings()->create([
            'read_on' => $data['readOn'],
            'meter_reading' => $meterReading,
            'meter_reset' => $this->meterReset,
            'consumption' => $data['consumption'] !== '' ? $data['consumption'] : null,
            'cost' => $data['cost'] !== '' ? $data['cost'] : null,
            'created_by' => auth()->id(),
        ]);

        $this->accountId = null;
        $this->dispatch('reading-recorded');
    }

    public function render(): View
    {
        return view('livewire.utilities.record-reading', [
            'account' => $this->accountId ? UtilityAccount::find($this->accountId) : null,
        ]);
    }
}
