@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $statusTone = fn (string $status) => match ($status) {
        'active' => 'positive',
        'suspended' => 'warning',
        'exited' => 'muted',
        default => 'neutral',
    };
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Cooperative</h1>
            <p class="mt-1 text-[14.5px] text-muted">Member registry and the share/savings contributions members make.</p>
        </div>
        @can('cooperative.create')
            <button type="button" wire:click="startAdding"
                    class="tap focusable flex shrink-0 items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                <x-icon name="plus" class="size-[18px]" />
                <span class="sr-only min-[420px]:not-sr-only">Add</span>
            </button>
        @endcan
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'active' => 'Active', 'suspended' => 'Suspended', 'exited' => 'Exited'] as $value => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $value }}')"
                    class="tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold {{ $statusFilter === $value ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($adding)
        <x-ui.panel :title="$editingId ? 'Edit member' : 'New member'" class="mt-5">
            <form wire:submit="save" class="space-y-4">
                @unless ($editingId)
                    <label class="block">
                        <span class="{{ $labelClass }}">Contact</span>
                        <select wire:model="contactId" class="{{ $inputClass }}">
                            <option value="">Choose a contact</option>
                            @foreach ($contacts as $contact)
                                <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                            @endforeach
                        </select>
                        @error('contactId') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                @endunless
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Membership number</span>
                        <input type="text" wire:model="membershipNumber" class="{{ $inputClass }}">
                        @error('membershipNumber') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Joined on</span>
                        <input type="date" wire:model="joinedOn" class="{{ $inputClass }}">
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
        @forelse ($members as $member)
            <div class="card p-4" wire:key="member-{{ $member->id }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[14.5px] font-bold text-ink">
                            {{ $member->contact->name ?? 'Member' }}
                            @if ($member->membership_number) · #{{ $member->membership_number }} @endif
                        </p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            Balance {{ number_format((float) $member->balance, 2) }}
                        </p>
                    </div>
                    <x-ui.status-badge :tone="$statusTone($member->status)" :label="ucfirst($member->status)" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('update', $member)
                        <button type="button" wire:click="edit('{{ $member->id }}')" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                    @endcan
                    @can('recordContribution', $member)
                        <button type="button" wire:click="$dispatch('open-contribution', { memberId: '{{ $member->id }}' })" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Record contribution</button>
                    @endcan
                    @can('delete', $member)
                        <button type="button" wire:click="delete('{{ $member->id }}')" wire:confirm="Remove this member's record?" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Delete</button>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-[13.5px] text-muted">No members recorded yet.</p>
        @endforelse
    </div>

    <livewire:cooperative.record-contribution />
</div>
