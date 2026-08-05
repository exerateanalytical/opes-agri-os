@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $statusTone = fn (string $status) => $status === 'active' ? 'positive' : 'muted';
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Utilities</h1>
            <p class="mt-1 text-[14.5px] text-muted">Electricity, water and other metered accounts, and how much they use.</p>
        </div>
        @can('utilities.create')
            <button type="button" wire:click="startAdding"
                    class="tap focusable flex shrink-0 items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                <x-icon name="plus" class="size-[18px]" />
                <span class="sr-only min-[420px]:not-sr-only">Add</span>
            </button>
        @endcan
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'electricity' => 'Electricity', 'water' => 'Water', 'internet' => 'Internet', 'gas' => 'Gas', 'other' => 'Other'] as $value => $label)
            <button type="button" wire:click="$set('typeFilter', '{{ $value }}')"
                    class="tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold {{ $typeFilter === $value ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($adding)
        <x-ui.panel :title="$editingId ? 'Edit utility account' : 'New utility account'" class="mt-5">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Type</span>
                        <select wire:model="utilityType" class="{{ $inputClass }}">
                            <option value="electricity">Electricity</option>
                            <option value="water">Water</option>
                            <option value="internet">Internet</option>
                            <option value="gas">Gas</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Farm (optional)</span>
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
                        <span class="{{ $labelClass }}">Provider</span>
                        <input type="text" wire:model="providerName" class="{{ $inputClass }}">
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Account number</span>
                        <input type="text" wire:model="accountNumber" class="{{ $inputClass }}">
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
        @forelse ($accounts as $account)
            @php($latest = $account->readings->first())
            <div class="card p-4" wire:key="account-{{ $account->id }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[14.5px] font-bold text-ink">
                            {{ ucfirst($account->utility_type) }}
                            @if ($account->provider_name) · {{ $account->provider_name }} @endif
                        </p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            @if ($account->farm) {{ $account->farm->name }} · @endif
                            @if ($latest) Last read {{ $latest->read_on?->format('d M Y') }} @else No readings yet @endif
                        </p>
                    </div>
                    <x-ui.status-badge :tone="$statusTone($account->status)" :label="ucfirst($account->status)" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('update', $account)
                        <button type="button" wire:click="edit('{{ $account->id }}')" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                    @endcan
                    @can('recordReading', $account)
                        <button type="button" wire:click="$dispatch('open-reading', { accountId: '{{ $account->id }}' })" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Record reading</button>
                    @endcan
                    @can('delete', $account)
                        <button type="button" wire:click="delete('{{ $account->id }}')" wire:confirm="Remove this utility account?" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Delete</button>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-[13.5px] text-muted">No utility accounts recorded yet.</p>
        @endforelse
    </div>

    <livewire:utilities.record-reading />
</div>
