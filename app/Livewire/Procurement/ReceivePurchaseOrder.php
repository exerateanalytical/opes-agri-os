<?php

namespace App\Livewire\Procurement;

use App\Models\PurchaseOrder;
use App\Services\Agri\PurchaseOrderReceiver;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * A dedicated modal for receiving a purchase order: per-line quantity
 * received, batch/expiry, and an optional expense toggle — the same shape
 * as the ordinary delivery form (App\Livewire\Products\Form), just scoped
 * to one PO's outstanding lines.
 */
class ReceivePurchaseOrder extends Component
{
    use AuthorizesRequests;

    public ?string $purchaseOrderId = null;

    /** @var array<int, array{purchase_order_line_id: string, label: string, quantity: string, batch_number: string, expires_on: string}> */
    public array $lines = [];

    public bool $recordExpense = false;

    public string $vatRate = '0';

    #[On('open-receive')]
    public function open(string $purchaseOrderId): void
    {
        $po = PurchaseOrder::with('lines.item')->findOrFail($purchaseOrderId);
        $this->authorize('receive', $po);

        $this->purchaseOrderId = $po->id;
        $this->lines = $po->lines
            ->reject(fn ($line) => $line->isFullyReceived())
            ->map(fn ($line) => [
                'purchase_order_line_id' => $line->id,
                'label' => $line->item?->name ?? $line->description ?? 'Line',
                'quantity' => (string) round((float) $line->quantity - (float) $line->quantity_received, 3),
                'batch_number' => '',
                'expires_on' => '',
            ])->values()->all();
        $this->recordExpense = false;
        $this->vatRate = '0';
    }

    public function close(): void
    {
        $this->purchaseOrderId = null;
    }

    public function save(PurchaseOrderReceiver $receiver): void
    {
        $data = $this->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:100'],
            'lines.*.expires_on' => ['nullable', 'date'],
            'recordExpense' => ['boolean'],
            'vatRate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $po = PurchaseOrder::findOrFail($this->purchaseOrderId);
        $this->authorize('receive', $po);

        $lines = collect($data['lines'])
            ->filter(fn (array $line) => (float) ($line['quantity'] ?? 0) > 0)
            ->map(fn (array $line) => [
                'purchase_order_line_id' => $line['purchase_order_line_id'],
                'quantity' => $line['quantity'],
                'batch_number' => $line['batch_number'] ?: null,
                'expires_on' => $line['expires_on'] ?: null,
            ])->all();

        try {
            $receiver->receive(
                $po,
                $lines,
                [
                    'record_expense' => $data['recordExpense'],
                    'vat_rate' => (float) $data['vatRate'],
                ],
                auth()->user(),
            );
        } catch (RuntimeException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        $this->purchaseOrderId = null;
        $this->dispatch('purchase-order-received');
    }

    public function render(): View
    {
        return view('livewire.procurement.receive-purchase-order', [
            'purchaseOrder' => $this->purchaseOrderId ? PurchaseOrder::find($this->purchaseOrderId) : null,
        ]);
    }
}
