@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
@endphp

<div>
    @if ($animal)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" wire:click.self="close">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface p-5 sm:rounded-2xl">
                <h2 class="text-[17px] font-bold text-ink">Record production</h2>
                <p class="mt-1 text-[13.5px] text-muted">{{ $animal->species }}{{ $animal->tag_number ? ' · #'.$animal->tag_number : '' }}</p>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <label class="block">
                        <span class="{{ $labelClass }}">Product (optional)</span>
                        <select wire:model="itemId" class="{{ $inputClass }}">
                            <option value="">No product — record only</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Quantity</span>
                        <input type="number" step="0.001" wire:model="quantity" autofocus class="{{ $inputClass }}">
                        @error('quantity') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Unit cost (optional)</span>
                        <input type="number" step="0.01" wire:model="unitCost" class="{{ $inputClass }}">
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Recorded on</span>
                        <input type="date" wire:model="recordedOn" class="{{ $inputClass }}">
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Notes</span>
                        <textarea wire:model="notes" rows="2" class="{{ $inputClass }} h-auto py-3"></textarea>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="tap focusable flex-1 rounded-xl bg-fill-brand px-5 py-3 text-[14.5px] font-semibold text-white">Save</button>
                        <button type="button" wire:click="close" class="tap focusable rounded-xl bg-surface-2 px-5 py-3 text-[14.5px] font-semibold text-ink-2">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
