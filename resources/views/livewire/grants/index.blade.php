@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $statusTone = fn (string $status) => match ($status) {
        'active' => 'positive',
        'completed' => 'muted',
        'cancelled' => 'negative',
        default => 'muted',
    };
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Grants</h1>
            <p class="mt-1 text-[14.5px] text-muted">Funded projects, and the grant money received and spent against them.</p>
        </div>
        @can('create', \App\Models\GrantProject::class)
            <button type="button" wire:click="startAdding"
                    class="tap focusable flex shrink-0 items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                <x-icon name="plus" class="size-[18px]" />
                <span class="sr-only min-[420px]:not-sr-only">Add</span>
            </button>
        @endcan
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'planned' => 'Planned', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $value }}')"
                    class="tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold {{ $statusFilter === $value ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($adding)
        <x-ui.panel :title="$editingId ? 'Edit grant project' : 'New grant project'" class="mt-5">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Name</span>
                        <input type="text" wire:model="name" class="{{ $inputClass }}">
                        @error('name') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Partner (optional)</span>
                        <select wire:model="partnerId" class="{{ $inputClass }}">
                            <option value="">No partner chosen</option>
                            @foreach ($partners as $partner)
                                <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <label class="block">
                        <span class="{{ $labelClass }}">Total amount</span>
                        <input type="number" step="0.01" wire:model="totalAmount" class="{{ $inputClass }}">
                        @error('totalAmount') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Currency</span>
                        <input type="text" wire:model="currency" maxlength="3" class="{{ $inputClass }} uppercase">
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Start date</span>
                        <input type="date" wire:model="startDate" class="{{ $inputClass }}">
                    </label>
                </div>
                <label class="block">
                    <span class="{{ $labelClass }}">End date (optional)</span>
                    <input type="date" wire:model="endDate" class="{{ $inputClass }}">
                    @error('endDate') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">Description</span>
                    <textarea wire:model="description" rows="2" class="{{ $inputClass }} h-auto py-3"></textarea>
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                    <button type="button" wire:click="$set('adding', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                </div>
            </form>
        </x-ui.panel>
    @endif

    <div class="mt-5 space-y-3">
        @forelse ($projects as $project)
            <div class="card p-4" wire:key="project-{{ $project->id }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[14.5px] font-bold text-ink">{{ $project->name }}</p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            @if ($project->partner) {{ $project->partner->name }} · @endif
                            {{ $project->currency }} {{ number_format((float) $project->received_amount, 2) }} received ·
                            {{ number_format((float) $project->spent_amount, 2) }} spent ·
                            {{ number_format($project->balance(), 2) }} left
                        </p>
                    </div>
                    <x-ui.status-badge :tone="$statusTone($project->status)" :label="ucfirst($project->status)" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('update', $project)
                        <button type="button" wire:click="edit('{{ $project->id }}')" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                    @endcan
                    @can('recordTransaction', $project)
                        <button type="button" wire:click="$dispatch('open-grant-transaction', { projectId: '{{ $project->id }}' })" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Record transaction</button>
                    @endcan
                    @can('delete', $project)
                        <button type="button" wire:click="delete('{{ $project->id }}')" wire:confirm="Remove this grant project?" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Delete</button>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-[13.5px] text-muted">No grant projects recorded yet.</p>
        @endforelse
    </div>

    <livewire:grants.record-transaction />
</div>
