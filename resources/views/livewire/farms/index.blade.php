@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $tabClass = fn (string $key) => 'tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold '
        .($tab === $key ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand');
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Farms</h1>
            <p class="mt-1 text-[14.5px] text-muted">Farms, their fields and the seasons you plan around.</p>
        </div>
    </div>

    <div class="mt-5 flex gap-2">
        <button type="button" wire:click="$set('tab', 'farms')" class="{{ $tabClass('farms') }}">Farms</button>
        <button type="button" wire:click="$set('tab', 'fields')" class="{{ $tabClass('fields') }}">Fields</button>
        <button type="button" wire:click="$set('tab', 'seasons')" class="{{ $tabClass('seasons') }}">Seasons</button>
    </div>

    {{-- ── Farms ────────────────────────────────────────────────────────── --}}
    @if ($tab === 'farms')
        <div class="mt-5">
            @can('farms.create')
                <button type="button" wire:click="startAddingFarm"
                        class="tap focusable mb-4 flex items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                    <x-icon name="plus" class="size-[18px]" />
                    Add farm
                </button>
            @endcan

            @if ($addingFarm)
                <x-ui.panel :title="$editingFarmId ? 'Edit farm' : 'New farm'" class="mb-4">
                    <form wire:submit="saveFarm" class="space-y-4">
                        <label class="block">
                            <span class="{{ $labelClass }}">Name</span>
                            <input type="text" wire:model="farmName" class="{{ $inputClass }}">
                            @error('farmName') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                        </label>
                        <label class="block">
                            <span class="{{ $labelClass }}">Location</span>
                            <input type="text" wire:model="farmLocation" class="{{ $inputClass }}">
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="{{ $labelClass }}">Size (hectares)</span>
                                <input type="number" step="0.01" wire:model="farmSize" class="{{ $inputClass }}">
                            </label>
                            <label class="block">
                                <span class="{{ $labelClass }}">Ownership</span>
                                <select wire:model="farmOwnership" class="{{ $inputClass }}">
                                    <option value="owned">Owned</option>
                                    <option value="leased">Leased</option>
                                    <option value="mixed">Mixed</option>
                                </select>
                            </label>
                        </div>
                        <label class="block">
                            <span class="{{ $labelClass }}">Notes</span>
                            <textarea wire:model="farmNotes" rows="2" class="{{ $inputClass }} h-auto py-3"></textarea>
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                            <button type="button" wire:click="$set('addingFarm', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                        </div>
                    </form>
                </x-ui.panel>
            @endif

            <div class="space-y-3">
                @forelse ($farms as $farm)
                    <div class="card flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate text-[14.5px] font-bold text-ink">{{ $farm->name }}</p>
                            <p class="mt-0.5 text-[12.5px] text-muted">
                                {{ $farm->fields_count }} {{ Str::plural('field', $farm->fields_count) }}
                                @if ($farm->size_hectares) · {{ $farm->size_hectares }} ha @endif
                                · {{ ucfirst($farm->ownership_type) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            @can('update', $farm)
                                <button type="button" wire:click="editFarm('{{ $farm->id }}')" class="focusable rounded-lg px-2.5 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                            @endcan
                            @can('delete', $farm)
                                <button type="button" wire:click="deleteFarm('{{ $farm->id }}')" wire:confirm="Delete {{ $farm->name }}?" class="focusable rounded-lg px-2.5 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Delete</button>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="text-[13.5px] text-muted">No farms yet.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ── Fields ───────────────────────────────────────────────────────── --}}
    @if ($tab === 'fields')
        <div class="mt-5">
            @can('farms.create')
                <button type="button" wire:click="startAddingField"
                        class="tap focusable mb-4 flex items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                    <x-icon name="plus" class="size-[18px]" />
                    Add field
                </button>
            @endcan

            @if ($addingField)
                <x-ui.panel :title="$editingFieldId ? 'Edit field' : 'New field'" class="mb-4">
                    <form wire:submit="saveField" class="space-y-4">
                        <label class="block">
                            <span class="{{ $labelClass }}">Farm</span>
                            <select wire:model="fieldFarmId" class="{{ $inputClass }}">
                                <option value="">Choose a farm</option>
                                @foreach ($farms as $farm)
                                    <option value="{{ $farm->id }}">{{ $farm->name }}</option>
                                @endforeach
                            </select>
                            @error('fieldFarmId') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                        </label>
                        <label class="block">
                            <span class="{{ $labelClass }}">Name</span>
                            <input type="text" wire:model="fieldName" class="{{ $inputClass }}">
                            @error('fieldName') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="{{ $labelClass }}">Area (hectares)</span>
                                <input type="number" step="0.01" wire:model="fieldArea" class="{{ $inputClass }}">
                            </label>
                            <label class="block">
                                <span class="{{ $labelClass }}">Ownership</span>
                                <select wire:model="fieldOwnership" class="{{ $inputClass }}">
                                    <option value="owned">Owned</option>
                                    <option value="leased">Leased</option>
                                </select>
                            </label>
                        </div>
                        @if ($fieldOwnership === 'leased')
                            <div class="grid grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="{{ $labelClass }}">Lease start</span>
                                    <input type="date" wire:model="fieldLeaseStart" class="{{ $inputClass }}">
                                </label>
                                <label class="block">
                                    <span class="{{ $labelClass }}">Lease end</span>
                                    <input type="date" wire:model="fieldLeaseEnd" class="{{ $inputClass }}">
                                </label>
                            </div>
                        @endif

                        {{-- Boundary: a plain list of points, not an interactive map.
                             The stored shape (array of {lat,lng}) is map-library-ready
                             for whenever one gets added. --}}
                        <div>
                            <span class="{{ $labelClass }}">Boundary points (optional)</span>
                            <div class="space-y-2">
                                @foreach ($fieldBoundary as $index => $point)
                                    <div class="flex items-center gap-2">
                                        <input type="number" step="0.0000001" wire:model="fieldBoundary.{{ $index }}.lat" placeholder="Latitude" class="{{ $inputClass }}">
                                        <input type="number" step="0.0000001" wire:model="fieldBoundary.{{ $index }}.lng" placeholder="Longitude" class="{{ $inputClass }}">
                                        <button type="button" wire:click="removeBoundaryPoint({{ $index }})" class="focusable shrink-0 rounded-lg p-2 text-negative hover:bg-tint-red">
                                            <x-icon name="ellipsis" class="size-[16px]" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" wire:click="addBoundaryPoint" class="focusable mt-2 text-[13px] font-semibold text-brand hover:underline">+ Add point</button>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                            <button type="button" wire:click="$set('addingField', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                        </div>
                    </form>
                </x-ui.panel>
            @endif

            <div class="space-y-3">
                @forelse ($fields as $field)
                    <div class="card flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate text-[14.5px] font-bold text-ink">{{ $field->name }}</p>
                            <p class="mt-0.5 text-[12.5px] text-muted">
                                {{ $field->farm->name }}
                                @if ($field->area_hectares) · {{ $field->area_hectares }} ha @endif
                                @if ($field->boundary) · {{ count($field->boundary) }} boundary points @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            @can('update', $field)
                                <button type="button" wire:click="editField('{{ $field->id }}')" class="focusable rounded-lg px-2.5 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                            @endcan
                            @can('delete', $field)
                                <button type="button" wire:click="deleteField('{{ $field->id }}')" wire:confirm="Delete {{ $field->name }}?" class="focusable rounded-lg px-2.5 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Delete</button>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="text-[13.5px] text-muted">No fields yet.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ── Seasons ──────────────────────────────────────────────────────── --}}
    @if ($tab === 'seasons')
        <div class="mt-5">
            @can('farms.create')
                <button type="button" wire:click="startAddingSeason"
                        class="tap focusable mb-4 flex items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                    <x-icon name="plus" class="size-[18px]" />
                    Add season
                </button>
            @endcan

            @if ($addingSeason)
                <x-ui.panel :title="$editingSeasonId ? 'Edit season' : 'New season'" class="mb-4">
                    <form wire:submit="saveSeason" class="space-y-4">
                        <label class="block">
                            <span class="{{ $labelClass }}">Name</span>
                            <input type="text" wire:model="seasonName" placeholder="e.g. 2026 Long Rains" class="{{ $inputClass }}">
                            @error('seasonName') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="{{ $labelClass }}">Starts</span>
                                <input type="date" wire:model="seasonStartsOn" class="{{ $inputClass }}">
                                @error('seasonStartsOn') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                            </label>
                            <label class="block">
                                <span class="{{ $labelClass }}">Ends</span>
                                <input type="date" wire:model="seasonEndsOn" class="{{ $inputClass }}">
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                            <button type="button" wire:click="$set('addingSeason', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                        </div>
                    </form>
                </x-ui.panel>
            @endif

            <div class="space-y-3">
                @forelse ($seasons as $season)
                    <div class="card flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate text-[14.5px] font-bold text-ink">{{ $season->name }}</p>
                            <p class="mt-0.5 text-[12.5px] text-muted">
                                {{ $season->starts_on->format('d M Y') }}
                                @if ($season->ends_on) – {{ $season->ends_on->format('d M Y') }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            @can('update', $season)
                                <button type="button" wire:click="editSeason('{{ $season->id }}')" class="focusable rounded-lg px-2.5 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                            @endcan
                            @can('delete', $season)
                                <button type="button" wire:click="deleteSeason('{{ $season->id }}')" wire:confirm="Delete {{ $season->name }}?" class="focusable rounded-lg px-2.5 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Delete</button>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="text-[13.5px] text-muted">No seasons yet.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
