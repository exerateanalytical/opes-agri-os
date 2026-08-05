@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $reasonTone = fn (string $reason) => match ($reason) {
        'purchase', 'harvest', 'livestock-production' => 'positive',
        'sale' => 'neutral',
        'credit', 'document-void' => 'warning',
        default => 'muted',
    };
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="min-w-0">
        <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Trace a batch</h1>
        <p class="mt-1 text-[14.5px] text-muted">Every movement recorded against a batch, from arrival to sale.</p>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <label class="block">
            <span class="{{ $labelClass }}">Item</span>
            <select wire:model.live="itemId" class="{{ $inputClass }}">
                <option value="">Choose an item</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="{{ $labelClass }}">Batch number</span>
            <select wire:model.live="batchNumber" class="{{ $inputClass }}" @disabled($itemId === '')>
                <option value="">Choose a batch</option>
                @foreach ($batches as $batch)
                    <option value="{{ $batch }}">{{ $batch }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($movements as $movement)
            <div class="card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[14.5px] font-bold text-ink">
                            {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                            @if ($movement->unit_cost) · {{ number_format((float) $movement->unit_cost, 2) }}/unit @endif
                        </p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            {{ $movement->occurred_at?->format('d M Y H:i') }}
                            @if ($movement->reference_type) · {{ class_basename($movement->reference_type) }} @endif
                        </p>
                    </div>
                    <x-ui.status-badge :tone="$reasonTone($movement->reason)" :label="ucfirst(str_replace('-', ' ', $movement->reason))" />
                </div>
            </div>
        @empty
            @if ($itemId !== '' && $batchNumber !== '')
                <p class="text-[13.5px] text-muted">No movements found for that batch.</p>
            @else
                <p class="text-[13.5px] text-muted">Choose an item and a batch to see its history.</p>
            @endif
        @endforelse
    </div>
</div>
