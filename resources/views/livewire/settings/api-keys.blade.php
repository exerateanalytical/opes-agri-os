@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';

    // "sales.issue" -> "Sales · Issue", so the picklist reads like the rest
    // of the app's permission language instead of raw dotted slugs.
    $abilityLabel = fn (string $slug) => collect(explode('.', $slug))
        ->map(fn ($part) => \Illuminate\Support\Str::headline($part))
        ->implode(' · ');
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('settings') }}" class="focusable flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand">
            <x-icon name="chevron-left" class="size-[18px]" />
        </a>
        <div>
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">API Keys</h1>
            <p class="mt-1 text-[14.5px] text-muted">
                Tokens for the OPES AGRI OS API — <code class="text-[13px]">/api/v1/…</code>. Each key can only do what you yourself are allowed to do.
            </p>
        </div>
    </div>

    {{-- The plaintext token, shown exactly once --}}
    @if ($newlyIssuedToken)
        <div class="card mt-5 border-brand/30 bg-tint-blue p-5" wire:key="new-token">
            <p class="text-[14px] font-bold text-ink">Copy this key now — it won't be shown again</p>
            <p class="mt-1 text-[13px] text-ink-2">
                Sanctum only stores its hash. If you lose it, revoke this key and create a new one.
            </p>
            <div class="mt-3 flex items-center gap-2">
                <code class="min-w-0 flex-1 truncate rounded-xl border border-border bg-surface px-3.5 py-3 text-[13px] text-ink" id="new-api-token">{{ $newlyIssuedToken }}</code>
                <button type="button"
                        x-data
                        x-on:click="navigator.clipboard.writeText(document.getElementById('new-api-token').textContent)"
                        class="tap focusable shrink-0 rounded-xl bg-surface-2 px-3.5 py-3 text-[13px] font-semibold text-ink-2 hover:bg-tint-blue hover:text-brand">
                    Copy
                </button>
            </div>
            <button type="button" wire:click="dismissNewToken"
                    class="focusable mt-3 text-[13px] font-semibold text-brand hover:underline">
                Done
            </button>
        </div>
    @endif

    <div class="mt-5 grid gap-4 lg:grid-cols-2">

        {{-- Create --}}
        <x-ui.panel title="New API key">
            @if (empty($availableAbilities))
                <p class="text-[13.5px] text-muted">You don't hold any grantable permissions in this business yet.</p>
            @else
                <form wire:submit="create" class="space-y-4">
                    <label class="block">
                        <span class="{{ $labelClass }}">Name</span>
                        <input type="text" wire:model="name" placeholder="e.g. Warehouse integration" class="{{ $inputClass }}">
                        @error('name') <p class="mt-1 text-[12.5px] font-medium text-warning">{{ $message }}</p> @enderror
                    </label>

                    <div>
                        <span class="{{ $labelClass }}">Abilities</span>
                        <div class="max-h-56 space-y-2 overflow-y-auto rounded-xl border border-border p-3">
                            @foreach ($availableAbilities as $ability)
                                <label class="flex items-center gap-2.5 text-[13.5px] text-ink">
                                    <input type="checkbox" wire:model="selectedAbilities" value="{{ $ability }}"
                                           class="size-4 rounded border-border text-brand focus:ring-brand/30">
                                    {{ $abilityLabel($ability) }}
                                </label>
                            @endforeach
                        </div>
                        @error('selectedAbilities') <p class="mt-1 text-[12.5px] font-medium text-warning">{{ $message }}</p> @enderror
                    </div>

                    <label class="block">
                        <span class="{{ $labelClass }}">Expires after (days, optional)</span>
                        <input type="number" min="1" max="3650" wire:model="expiresInDays" placeholder="Never" class="{{ $inputClass }}">
                        @error('expiresInDays') <p class="mt-1 text-[12.5px] font-medium text-warning">{{ $message }}</p> @enderror
                    </label>

                    <button type="submit"
                            class="tap focusable flex h-12 w-full items-center justify-center rounded-xl bg-fill-brand text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                        Create key
                    </button>
                </form>
            @endif
        </x-ui.panel>

        {{-- List --}}
        <x-ui.panel title="Active keys" body-class="-mx-1.5">
            @if ($tokens->isEmpty())
                <p class="px-1.5 text-[13.5px] text-muted">No API keys yet.</p>
            @else
                <div class="divide-y divide-border">
                    @foreach ($tokens as $token)
                        <div class="flex items-center gap-3 px-1.5 py-3" wire:key="token-{{ $token->id }}">
                            <span class="flex size-[38px] shrink-0 items-center justify-center rounded-xl bg-tint-blue">
                                <x-icon name="key" class="size-[18px] text-brand" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[14px] font-semibold text-ink">{{ $token->name }}</p>
                                <p class="mt-0.5 truncate text-[12.5px] text-muted">
                                    {{ $token->last_used_at ? 'Last used '.$token->last_used_at->diffForHumans() : 'Never used' }}
                                    @if ($token->expires_at)
                                        · Expires {{ $token->expires_at->format('d M Y') }}
                                    @endif
                                </p>
                            </div>
                            <button type="button" wire:click="revoke({{ $token->id }})"
                                    wire:confirm="Revoke {{ $token->name }}? Anything using this key will stop working immediately."
                                    class="focusable shrink-0 rounded-lg px-2.5 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">
                                Revoke
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.panel>
    </div>
</div>
