<?php

namespace App\Livewire\Grants;

use App\Enums\PaymentMethod;
use App\Models\GrantProject;
use App\Models\GrantTransaction;
use App\Services\Grants\GrantTransactionRecorder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

class RecordTransaction extends Component
{
    use AuthorizesRequests;

    public ?string $projectId = null;

    public string $type = 'receipt';

    public string $amount = '';

    public string $method = 'cash';

    public string $transactionDate = '';

    public string $description = '';

    #[On('open-grant-transaction')]
    public function open(string $projectId): void
    {
        $project = GrantProject::findOrFail($projectId);
        $this->authorize('recordTransaction', $project);

        $this->projectId = $project->id;
        $this->type = 'receipt';
        $this->amount = '';
        $this->method = 'cash';
        $this->transactionDate = now()->toDateString();
        $this->description = '';
    }

    public function close(): void
    {
        $this->projectId = null;
    }

    public function save(GrantTransactionRecorder $recorder): void
    {
        $data = $this->validate([
            'type' => ['required', 'in:'.implode(',', GrantTransaction::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'transactionDate' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $project = GrantProject::findOrFail($this->projectId);
        $this->authorize('recordTransaction', $project);

        try {
            $recorder->record(
                $project,
                auth()->user(),
                $data['type'],
                (float) $data['amount'],
                PaymentMethod::from($data['method']),
                $data['transactionDate'],
                ['description' => $data['description'] ?: null],
            );
        } catch (RuntimeException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->projectId = null;
        $this->dispatch('grant-transaction-recorded');
    }

    public function render(): View
    {
        return view('livewire.grants.record-transaction', [
            'project' => $this->projectId ? GrantProject::find($this->projectId) : null,
        ]);
    }
}
