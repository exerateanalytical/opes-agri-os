<?php

namespace App\Livewire\Procurement;

use App\Models\Contact;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Services\Agri\PurchaseOrderIssuer;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * The purchase order list and its create/edit form. Receiving is its own
 * component (ReceivePurchaseOrder) — a distinct lifecycle action with its
 * own per-line quantity/batch shape, not a field edit.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $statusFilter = '';

    public bool $adding = false;

    public ?string $editingId = null;

    public string $supplierId = '';

    public string $orderDate = '';

    public string $expectedDate = '';

    public string $notes = '';

    /** @var array<int, array{item_id: string, description: string, quantity: string, unit_cost: string}> */
    public array $lines = [];

    public function mount(): void
    {
        Gate::authorize('procurement.view');
    }

    public function startAdding(): void
    {
        $this->authorize('create', PurchaseOrder::class);
        $this->resetForm();
        $this->orderDate = now()->toDateString();
        $this->adding = true;
    }

    public function edit(string $purchaseOrderId): void
    {
        $po = PurchaseOrder::with('lines')->findOrFail($purchaseOrderId);
        $this->authorize('update', $po);

        $this->editingId = $po->id;
        $this->supplierId = (string) $po->supplier_id;
        $this->orderDate = (string) $po->order_date?->toDateString();
        $this->expectedDate = (string) $po->expected_date?->toDateString();
        $this->notes = (string) $po->notes;
        $this->lines = $po->lines->map(fn ($line) => [
            'item_id' => (string) $line->item_id,
            'description' => (string) $line->description,
            'quantity' => (string) $line->quantity,
            'unit_cost' => (string) $line->unit_cost,
        ])->all();
        $this->adding = true;
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_id' => '', 'description' => '', 'quantity' => '', 'unit_cost' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        $data = $this->validate([
            'supplierId' => ['nullable', 'exists:contacts,id'],
            'orderDate' => ['required', 'date'],
            'expectedDate' => ['nullable', 'date', 'after_or_equal:orderDate'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        if ($this->editingId) {
            $po = PurchaseOrder::findOrFail($this->editingId);
            $this->authorize('update', $po);
        } else {
            $this->authorize('create', PurchaseOrder::class);
            $po = PurchaseOrder::create([
                'supplier_id' => $data['supplierId'] ?: null,
                'number' => null,
                'status' => 'draft',
                'order_date' => $data['orderDate'],
                'created_by' => auth()->id(),
            ]);
        }

        $po->update([
            'supplier_id' => $data['supplierId'] ?: null,
            'order_date' => $data['orderDate'],
            'expected_date' => $data['expectedDate'] ?: null,
            'notes' => $data['notes'] ?: null,
        ]);

        $po->lines()->delete();

        foreach ($data['lines'] as $line) {
            $po->lines()->create([
                'item_id' => $line['item_id'] ?: null,
                'description' => $line['description'] ?? null,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
            ]);
        }

        $po->recalculateTotals();

        $this->resetForm();
        $this->adding = false;
    }

    public function issue(string $purchaseOrderId, PurchaseOrderIssuer $issuer): void
    {
        $po = PurchaseOrder::findOrFail($purchaseOrderId);
        $this->authorize('issue', $po);

        try {
            $issuer->issue($po, auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('issue', $e->getMessage());
        }
    }

    /** No-op body — Livewire re-renders the list on any listened event. */
    #[On('purchase-order-received')]
    public function refreshAfterReceive(): void {}

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'supplierId', 'orderDate', 'expectedDate', 'notes', 'lines']);
        $this->lines = [['item_id' => '', 'description' => '', 'quantity' => '', 'unit_cost' => '']];
    }

    public function render(): View
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'lines'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.procurement.index', [
            'orders' => $orders,
            'suppliers' => Contact::query()->where('type', 'supplier')->orderBy('name')->get(),
            'items' => Item::query()->products()->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Purchase orders', 'active' => 'procurement']);
    }
}
