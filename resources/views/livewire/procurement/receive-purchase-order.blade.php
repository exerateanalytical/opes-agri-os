@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
@endphp

<div>
    @if ($purchaseOrder)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" wire:click.self="close">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface p-5 sm:rounded-2xl">
                <h2 class="text-[17px] font-bold text-ink">Receive {{ $purchaseOrder->number }}</h2>
                <p class="mt-1 text-[13.5px] text-muted">Enter what actually arrived.</p>

                <form wire:submit="save" class="mt-4 space-y-4">
                    @error('lines') <p class="text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    @foreach ($lines as $index => $line)
                        <div class="rounded-xl border border-border p-3" wire:key="rline-{{ $index }}">
                            <p class="text-[13.5px] font-semibold text-ink">{{ $line['label'] }}</p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <label class="block">
                                    <span class="{{ $labelClass }}">Quantity received</span>
                                    <input type="number" step="0.001" wire:model="lines.{{ $index }}.quantity" class="{{ $inputClass }}">
                                </label>
                                <label class="block">
                                    <span class="{{ $labelClass }}">Batch (optional)</span>
                                    <input type="text" wire:model="lines.{{ $index }}.batch_number" class="{{ $inputClass }}">
                                </label>
                            </div>
                            <label class="mt-2 block">
                                <span class="{{ $labelClass }}">Expires on (optional)</span>
                                <input type="date" wire:model="lines.{{ $index }}.expires_on" class="{{ $inputClass }}">
                            </label>
                        </div>
                    @endforeach

                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="recordExpense" class="size-4 rounded border-border">
                        <span class="text-[13.5px] font-semibold text-ink-2">Also record this as an expense</span>
                    </label>
                    @if ($recordExpense)
                        <label class="block">
                            <span class="{{ $labelClass }}">VAT rate (%)</span>
                            <input type="number" step="0.01" wire:model="vatRate" class="{{ $inputClass }}">
                        </label>
                    @endif

                    <div class="flex gap-2">
                        <button type="submit" class="tap focusable flex-1 rounded-xl bg-fill-brand px-5 py-3 text-[14.5px] font-semibold text-white">Save receipt</button>
                        <button type="button" wire:click="close" class="tap focusable rounded-xl bg-surface-2 px-5 py-3 text-[14.5px] font-semibold text-ink-2">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
