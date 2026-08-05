@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $statusTone = fn (string $status) => match ($status) {
        'received' => 'positive',
        'partially_received', 'issued' => 'warning',
        'cancelled' => 'muted',
        default => 'neutral',
    };
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Purchase orders</h1>
            <p class="mt-1 text-[14.5px] text-muted">Purchase orders for farm inputs and anything else you stock.</p>
        </div>
        @can('procurement.create')
            <button type="button" wire:click="startAdding"
                    class="tap focusable flex shrink-0 items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                <x-icon name="plus" class="size-[18px]" />
                <span class="sr-only min-[420px]:not-sr-only">Add</span>
            </button>
        @endcan
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'draft' => 'Draft', 'issued' => 'Issued', 'partially_received' => 'Partially received', 'received' => 'Received', 'cancelled' => 'Cancelled'] as $value => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $value }}')"
                    class="tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold {{ $statusFilter === $value ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($adding)
        <x-ui.panel :title="$editingId ? 'Edit purchase order' : 'New purchase order'" class="mt-5">
            <form wire:submit="save" class="space-y-4">
                <label class="block">
                    <span class="{{ $labelClass }}">Supplier</span>
                    <select wire:model="supplierId" class="{{ $inputClass }}">
                        <option value="">No supplier chosen</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Order date</span>
                        <input type="date" wire:model="orderDate" class="{{ $inputClass }}">
                        @error('orderDate') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Expected date</span>
                        <input type="date" wire:model="expectedDate" class="{{ $inputClass }}">
                    </label>
                </div>
                <label class="block">
                    <span class="{{ $labelClass }}">Notes</span>
                    <textarea wire:model="notes" rows="2" class="{{ $inputClass }} h-auto py-3"></textarea>
                </label>

                <div class="space-y-2">
                    <span class="{{ $labelClass }}">Lines</span>
                    @foreach ($lines as $index => $line)
                        <div class="grid grid-cols-12 gap-2" wire:key="line-{{ $index }}">
                            <select wire:model="lines.{{ $index }}.item_id" class="{{ $inputClass }} col-span-5">
                                <option value="">Item</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.001" wire:model="lines.{{ $index }}.quantity" placeholder="Qty" class="{{ $inputClass }} col-span-3">
                            <input type="number" step="0.01" wire:model="lines.{{ $index }}.unit_cost" placeholder="Unit cost" class="{{ $inputClass }} col-span-3">
                            <button type="button" wire:click="removeLine({{ $index }})" class="focusable col-span-1 rounded-lg text-negative hover:bg-tint-red">
                                <x-icon name="trash" class="mx-auto size-[18px]" />
                            </button>
                        </div>
                    @endforeach
                    @error('lines') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    <button type="button" wire:click="addLine" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">+ Add line</button>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                    <button type="button" wire:click="$set('adding', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                </div>
            </form>
        </x-ui.panel>
    @endif

    <div class="mt-5 space-y-3">
        @forelse ($orders as $order)
            <div class="card p-4" wire:key="po-{{ $order->id }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[14.5px] font-bold text-ink">
                            {{ $order->number ?? 'Draft' }}
                            @if ($order->supplier) · {{ $order->supplier->name }} @endif
                        </p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            {{ $order->order_date?->format('d M Y') }} · {{ number_format((float) $order->total, 2) }}
                        </p>
                    </div>
                    <x-ui.status-badge :tone="$statusTone($order->status)" :label="ucfirst(str_replace('_', ' ', $order->status))" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('update', $order)
                        @if ($order->status === 'draft')
                            <button type="button" wire:click="edit('{{ $order->id }}')" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                        @endif
                    @endcan
                    @can('issue', $order)
                        @if ($order->status === 'draft')
                            <button type="button" wire:click="issue('{{ $order->id }}')" wire:confirm="Issue this purchase order?" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Issue</button>
                        @endif
                    @endcan
                    @can('receive', $order)
                        @if (in_array($order->status, ['issued', 'partially_received']))
                            <button type="button" wire:click="$dispatch('open-receive', { purchaseOrderId: '{{ $order->id }}' })" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Receive</button>
                        @endif
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-[13.5px] text-muted">No purchase orders yet.</p>
        @endforelse
    </div>

    <livewire:procurement.receive-purchase-order />
</div>
