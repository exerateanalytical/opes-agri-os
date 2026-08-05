@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $statusTone = fn (string $status) => match ($status) {
        'harvested' => 'positive',
        'growing', 'planted' => 'warning',
        'closed' => 'muted',
        default => 'neutral',
    };
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Crops</h1>
            <p class="mt-1 text-[14.5px] text-muted">Planting plans, growth tracking and harvests, field by field.</p>
        </div>
        @can('crops.create')
            <button type="button" wire:click="startAdding"
                    class="tap focusable flex shrink-0 items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                <x-icon name="plus" class="size-[18px]" />
                <span class="sr-only min-[420px]:not-sr-only">Add</span>
            </button>
        @endcan
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'planned' => 'Planned', 'planted' => 'Planted', 'growing' => 'Growing', 'harvested' => 'Harvested', 'closed' => 'Closed'] as $value => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $value }}')"
                    class="tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold {{ $statusFilter === $value ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($adding)
        <x-ui.panel :title="$editingId ? 'Edit crop cycle' : 'New crop cycle'" class="mt-5">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Field</span>
                        <select wire:model="fieldId" class="{{ $inputClass }}">
                            <option value="">Choose a field</option>
                            @foreach ($fields as $field)
                                <option value="{{ $field->id }}">{{ $field->farm->name }} — {{ $field->name }}</option>
                            @endforeach
                        </select>
                        @error('fieldId') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Season</span>
                        <select wire:model="seasonId" class="{{ $inputClass }}">
                            <option value="">Choose a season</option>
                            @foreach ($seasons as $season)
                                <option value="{{ $season->id }}">{{ $season->name }}</option>
                            @endforeach
                        </select>
                        @error('seasonId') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                </div>
                <label class="block">
                    <span class="{{ $labelClass }}">Crop (product)</span>
                    <select wire:model="itemId" class="{{ $inputClass }}">
                        <option value="">No crop chosen yet</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Planned planting</span>
                        <input type="date" wire:model="plannedPlantingDate" class="{{ $inputClass }}">
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Planned harvest</span>
                        <input type="date" wire:model="plannedHarvestDate" class="{{ $inputClass }}">
                    </label>
                </div>
                <label class="block">
                    <span class="{{ $labelClass }}">Planned yield</span>
                    <input type="number" step="0.001" wire:model="plannedYieldQty" class="{{ $inputClass }}">
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">Notes</span>
                    <textarea wire:model="notes" rows="2" class="{{ $inputClass }} h-auto py-3"></textarea>
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                    <button type="button" wire:click="$set('adding', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                </div>
            </form>
        </x-ui.panel>
    @endif

    <div class="mt-5 space-y-3">
        @forelse ($cycles as $cycle)
            <div class="card p-4" wire:key="cycle-{{ $cycle->id }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[14.5px] font-bold text-ink">
                            {{ $cycle->field->name }}
                            @if ($cycle->item) · {{ $cycle->item->name }} @endif
                        </p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            {{ $cycle->season->name }}
                            @if ($cycle->growth_stage) · {{ ucfirst($cycle->growth_stage) }} @endif
                            @if ($cycle->actual_yield_qty) · {{ $cycle->actual_yield_qty }} {{ $cycle->yield_unit }} harvested @endif
                        </p>
                    </div>
                    <x-ui.status-badge :tone="$statusTone($cycle->status)" :label="ucfirst($cycle->status)" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('update', $cycle)
                        @if ($cycle->status === 'planned')
                            <button type="button" wire:click="markPlanted('{{ $cycle->id }}')" class="focusable rounded-lg bg-surface-2 px-3 py-1.5 text-[12.5px] font-semibold text-ink-2 hover:bg-tint-blue hover:text-brand">Mark planted</button>
                        @endif
                        @if (in_array($cycle->status, ['planned', 'planted']))
                            <button type="button" wire:click="markGrowing('{{ $cycle->id }}')" class="focusable rounded-lg bg-surface-2 px-3 py-1.5 text-[12.5px] font-semibold text-ink-2 hover:bg-tint-blue hover:text-brand">Mark growing</button>
                        @endif
                        <button type="button" wire:click="edit('{{ $cycle->id }}')" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                    @endcan
                    @can('recordHarvest', $cycle)
                        @if (! in_array($cycle->status, ['harvested', 'closed']))
                            <button type="button" wire:click="$dispatch('open-harvest', { cropCycleId: '{{ $cycle->id }}' })" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Record harvest</button>
                        @endif
                    @endcan
                    @can('update', $cycle)
                        @if (! in_array($cycle->status, ['harvested', 'closed']))
                            <button type="button" wire:click="close('{{ $cycle->id }}')" wire:confirm="Close this cycle without a harvest?" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Close</button>
                        @endif
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-[13.5px] text-muted">No crop cycles yet.</p>
        @endforelse
    </div>

    <livewire:crops.record-harvest />
</div>
