@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $statusTone = fn (string $status) => match ($status) {
        'active' => 'positive',
        'sold' => 'neutral',
        'deceased', 'culled' => 'muted',
        default => 'neutral',
    };
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Livestock</h1>
            <p class="mt-1 text-[14.5px] text-muted">Animal records, health and vaccinations, and what they produce.</p>
        </div>
        @can('livestock.create')
            <button type="button" wire:click="startAdding"
                    class="tap focusable flex shrink-0 items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                <x-icon name="plus" class="size-[18px]" />
                <span class="sr-only min-[420px]:not-sr-only">Add</span>
            </button>
        @endcan
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'active' => 'Active', 'sold' => 'Sold', 'deceased' => 'Deceased', 'culled' => 'Culled'] as $value => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $value }}')"
                    class="tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold {{ $statusFilter === $value ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($adding)
        <x-ui.panel :title="$editingId ? 'Edit animal' : 'New animal'" class="mt-5">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Species</span>
                        <input type="text" wire:model="species" class="{{ $inputClass }}">
                        @error('species') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Breed</span>
                        <input type="text" wire:model="breed" class="{{ $inputClass }}">
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Tag number</span>
                        <input type="text" wire:model="tagNumber" class="{{ $inputClass }}">
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Farm</span>
                        <select wire:model="farmId" class="{{ $inputClass }}">
                            <option value="">No farm chosen</option>
                            @foreach ($farms as $farm)
                                <option value="{{ $farm->id }}">{{ $farm->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Sex</span>
                        <select wire:model="sex" class="{{ $inputClass }}">
                            <option value="">Unknown</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Date of birth</span>
                        <input type="date" wire:model="dateOfBirth" class="{{ $inputClass }}">
                    </label>
                </div>
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
        @forelse ($animals as $animal)
            <div class="card p-4" wire:key="animal-{{ $animal->id }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[14.5px] font-bold text-ink">
                            {{ $animal->species }}
                            @if ($animal->tag_number) · #{{ $animal->tag_number }} @endif
                        </p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            @if ($animal->breed) {{ $animal->breed }} · @endif
                            @if ($animal->farm) {{ $animal->farm->name }} @endif
                        </p>
                    </div>
                    <x-ui.status-badge :tone="$statusTone($animal->status)" :label="ucfirst($animal->status)" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('update', $animal)
                        <button type="button" wire:click="edit('{{ $animal->id }}')" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                    @endcan
                    @can('recordHealth', $animal)
                        <button type="button" wire:click="$dispatch('open-health', { animalId: '{{ $animal->id }}' })" class="focusable rounded-lg bg-surface-2 px-3 py-1.5 text-[12.5px] font-semibold text-ink-2 hover:bg-tint-blue hover:text-brand">Record health</button>
                    @endcan
                    @can('recordProduction', $animal)
                        <button type="button" wire:click="$dispatch('open-production', { animalId: '{{ $animal->id }}' })" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Record production</button>
                    @endcan
                    @can('delete', $animal)
                        <button type="button" wire:click="delete('{{ $animal->id }}')" wire:confirm="Remove this animal's record?" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Delete</button>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-[13.5px] text-muted">No animals recorded yet.</p>
        @endforelse
    </div>

    <livewire:livestock.record-health />
    <livewire:livestock.record-production />
</div>
