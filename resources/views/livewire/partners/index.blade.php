@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $statusTone = fn (string $status) => $status === 'active' ? 'positive' : 'muted';
    $typeLabels = ['ngo' => 'NGO', 'donor' => 'Donor', 'government' => 'Government', 'cooperative_partner' => 'Cooperative partner', 'other' => 'Other'];
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Partners</h1>
            <p class="mt-1 text-[14.5px] text-muted">The NGOs, donors and government partners you work with, and a log of contact with each.</p>
        </div>
        @can('create', \App\Models\Partner::class)
            <button type="button" wire:click="startAdding"
                    class="tap focusable flex shrink-0 items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                <x-icon name="plus" class="size-[18px]" />
                <span class="sr-only min-[420px]:not-sr-only">Add</span>
            </button>
        @endcan
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (['' => 'All'] + $typeLabels as $value => $label)
            <button type="button" wire:click="$set('typeFilter', '{{ $value }}')"
                    class="tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold {{ $typeFilter === $value ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($adding)
        <x-ui.panel :title="$editingId ? 'Edit partner' : 'New partner'" class="mt-5">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Name</span>
                        <input type="text" wire:model="name" class="{{ $inputClass }}">
                        @error('name') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Type</span>
                        <select wire:model="type" class="{{ $inputClass }}">
                            @foreach ($typeLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Contact person</span>
                        <input type="text" wire:model="contactPerson" class="{{ $inputClass }}">
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Phone</span>
                        <input type="text" wire:model="phone" class="{{ $inputClass }}">
                    </label>
                </div>
                <label class="block">
                    <span class="{{ $labelClass }}">Email</span>
                    <input type="email" wire:model="email" class="{{ $inputClass }}">
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
        @forelse ($partners as $partner)
            @php($latest = $partner->interactions->first())
            <div class="card p-4" wire:key="partner-{{ $partner->id }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[14.5px] font-bold text-ink">{{ $partner->name }}</p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            {{ $typeLabels[$partner->type] ?? ucfirst($partner->type) }}
                            @if ($partner->contact_person) · {{ $partner->contact_person }} @endif
                            @if ($latest) · Last contact {{ $latest->interaction_date?->format('d M Y') }} @endif
                        </p>
                    </div>
                    <x-ui.status-badge :tone="$statusTone($partner->status)" :label="ucfirst($partner->status)" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('update', $partner)
                        <button type="button" wire:click="edit('{{ $partner->id }}')" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                    @endcan
                    @can('recordInteraction', $partner)
                        <button type="button" wire:click="$dispatch('open-interaction', { partnerId: '{{ $partner->id }}' })" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Log interaction</button>
                    @endcan
                    @can('delete', $partner)
                        <button type="button" wire:click="delete('{{ $partner->id }}')" wire:confirm="Remove this partner?" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Delete</button>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-[13.5px] text-muted">No partners recorded yet.</p>
        @endforelse
    </div>

    <livewire:partners.record-interaction />
</div>
