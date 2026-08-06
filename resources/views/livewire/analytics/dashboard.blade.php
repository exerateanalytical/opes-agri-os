@php
    use App\Support\Money;

    $inputClass = 'h-11 w-full rounded-xl border border-border bg-surface px-3.5 text-[14px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[12.5px] font-semibold text-ink-2';

    // Turn a plain [label, value] trend array into the {label, value, highlight}
    // shape x-ui.bar-chart expects — the same peak-highlight convention Reports
    // uses for its own series.
    $toSeries = function (array $points, string $key) {
        $series = collect($points)->map(fn (array $p) => ['label' => $p['label'], 'value' => (float) $p[$key]]);
        $peak = $series->max('value');

        return $series->map(fn (array $p) => [...$p, 'highlight' => $peak > 0 && $p['value'] === $peak])->all();
    };

    $trendTitle = $summary['range']['from'] ? 'Selected Range' : 'Last 6 Months';
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Analytics</h1>
            <p class="mt-1 text-[14.5px] text-muted">A cross-module operations picture — crops, livestock, procurement, assets, cooperative and grants.</p>
        </div>
        @can('analytics.export')
            <button type="button" wire:click="exportCsv"
                class="h-11 shrink-0 rounded-xl border border-border bg-surface px-4 text-[13.5px] font-semibold text-ink-2 hover:bg-surface-2">
                Export CSV
            </button>
        @endcan
    </div>

    {{-- Date range + breakdown filters --}}
    <x-ui.panel title="Filters" class="mt-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="{{ $labelClass }}" for="an-from">From</label>
                <input id="an-from" type="date" wire:model.live="from" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}" for="an-to">To</label>
                <input id="an-to" type="date" wire:model.live="to" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}" for="an-crops-group">Crops: view by</label>
                <select id="an-crops-group" wire:model.live="cropsGroupBy" class="{{ $inputClass }}">
                    <option value="">Company total</option>
                    <option value="farm">By farm</option>
                </select>
            </div>
            <div>
                <label class="{{ $labelClass }}" for="an-coop-group">Cooperative: view by</label>
                <select id="an-coop-group" wire:model.live="cooperativeGroupBy" class="{{ $inputClass }}">
                    <option value="">Company total</option>
                    <option value="member">By member</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="clearRange"
                    class="h-11 w-full rounded-xl border border-border bg-surface px-4 text-[13.5px] font-semibold text-ink-2 hover:bg-surface-2">
                    Reset to default (6mo)
                </button>
            </div>
        </div>
        @error('to')
            <p class="mt-2 text-[12.5px] font-medium text-negative">{{ $message }}</p>
        @enderror
        @if ($summary['range']['from'])
            <p class="mt-2 text-[12.5px] text-muted">Showing {{ $summary['range']['from'] }} to {{ $summary['range']['to'] }}.</p>
        @else
            <p class="mt-2 text-[12.5px] text-muted">Showing all-time totals (trend row uses the fixed 6-month window). Pick a From and To date to narrow every section to a custom range.</p>
        @endif
    </x-ui.panel>

    {{-- Crops --}}
    <x-ui.panel title="Crop Yield" class="mt-4">
        @if (! $summary['crops']['enabled'])
            <p class="py-6 text-center text-[13.5px] text-muted">Crop management is switched off for this business.</p>
        @else
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div>
                    <p class="text-[12.5px] font-medium text-muted">Crop cycles</p>
                    <p class="tnum text-[18px] font-bold text-ink">{{ $summary['crops']['cycle_count'] }}</p>
                </div>
                <div>
                    <p class="text-[12.5px] font-medium text-muted">Harvested</p>
                    <p class="tnum text-[18px] font-bold text-ink">{{ $summary['crops']['harvested_count'] }}</p>
                </div>
                <div>
                    <p class="text-[12.5px] font-medium text-muted">Planned yield</p>
                    <p class="tnum text-[18px] font-bold text-ink">{{ number_format($summary['crops']['planned_yield'], 2) }}</p>
                </div>
                <div>
                    <p class="text-[12.5px] font-medium text-muted">Actual yield</p>
                    <p class="tnum text-[18px] font-bold text-ink">
                        {{ number_format($summary['crops']['actual_yield'], 2) }}
                        @if ($summary['crops']['yield_attainment_pct'] !== null)
                            <span class="text-[13px] font-semibold text-muted">({{ $summary['crops']['yield_attainment_pct'] }}% of plan)</span>
                        @endif
                    </p>
                </div>
            </div>

            @if (! empty($summary['crops']['by_farm']))
                <div class="mt-4 overflow-x-auto border-t border-border pt-3">
                    <table class="w-full text-left text-[13px]">
                        <thead>
                            <tr class="text-[12px] font-semibold uppercase tracking-wide text-muted">
                                <th class="pb-2 pr-3">Farm</th>
                                <th class="pb-2 pr-3">Cycles</th>
                                <th class="pb-2 pr-3">Planned</th>
                                <th class="pb-2">Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['crops']['by_farm'] as $row)
                                <tr class="border-t border-border/60">
                                    <td class="py-1.5 pr-3 text-ink">{{ $row['farm_name'] }}</td>
                                    <td class="py-1.5 pr-3 tnum text-ink-2">{{ $row['cycle_count'] }}</td>
                                    <td class="py-1.5 pr-3 tnum text-ink-2">{{ number_format($row['planned_yield'], 2) }}</td>
                                    <td class="py-1.5 tnum text-ink-2">{{ number_format($row['actual_yield'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <button type="button" wire:click="toggleDrilldown('crops')"
                class="mt-3 text-[13px] font-semibold text-brand hover:underline">
                {{ $drilldown === 'crops' ? 'Hide details' : 'View details' }}
            </button>

            @if ($drilldown === 'crops')
                <div class="mt-3 overflow-x-auto border-t border-border pt-3">
                    @if ($drilldownTotal > 200)
                        <p class="pt-3 text-[12px] text-muted">Showing 200 of {{ number_format($drilldownTotal) }} matching records.</p>
                    @endif
                    <table class="w-full text-left text-[13px]">
                        <thead>
                            <tr class="text-[12px] font-semibold uppercase tracking-wide text-muted">
                                <th class="pb-2 pr-3">Field</th>
                                <th class="pb-2 pr-3">Status</th>
                                <th class="pb-2 pr-3">Planned yield</th>
                                <th class="pb-2">Actual yield</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($drilldownRows as $cycle)
                                <tr class="border-t border-border/60">
                                    <td class="py-1.5 pr-3 text-ink">{{ $cycle->field?->name }} <span class="text-muted">({{ $cycle->field?->farm?->name }})</span></td>
                                    <td class="py-1.5 pr-3 text-ink-2">{{ $cycle->status }}</td>
                                    <td class="py-1.5 pr-3 tnum text-ink-2">{{ number_format((float) $cycle->planned_yield_qty, 2) }}</td>
                                    <td class="py-1.5 tnum text-ink-2">{{ number_format((float) $cycle->actual_yield_qty, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-3 text-center text-muted">No crop cycles in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </x-ui.panel>

    {{-- Livestock --}}
    <x-ui.panel title="Livestock Production" class="mt-4">
        @if (! $summary['livestock']['enabled'])
            <p class="py-6 text-center text-[13.5px] text-muted">Livestock is switched off for this business.</p>
        @else
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div>
                    <p class="text-[12.5px] font-medium text-muted">Animals</p>
                    <p class="tnum text-[18px] font-bold text-ink">{{ $summary['livestock']['animal_count'] }}</p>
                </div>
                <div>
                    <p class="text-[12.5px] font-medium text-muted">Production records</p>
                    <p class="tnum text-[18px] font-bold text-ink">{{ $summary['livestock']['production_records'] }}</p>
                </div>
                <div>
                    <p class="text-[12.5px] font-medium text-muted">Production volume</p>
                    <p class="tnum text-[18px] font-bold text-ink">{{ number_format($summary['livestock']['production_volume'], 2) }}</p>
                </div>
            </div>

            <button type="button" wire:click="toggleDrilldown('livestock')"
                class="mt-3 text-[13px] font-semibold text-brand hover:underline">
                {{ $drilldown === 'livestock' ? 'Hide details' : 'View details' }}
            </button>

            @if ($drilldown === 'livestock')
                <div class="mt-3 overflow-x-auto border-t border-border pt-3">
                    @if ($drilldownTotal > 200)
                        <p class="pt-3 text-[12px] text-muted">Showing 200 of {{ number_format($drilldownTotal) }} matching records.</p>
                    @endif
                    <table class="w-full text-left text-[13px]">
                        <thead>
                            <tr class="text-[12px] font-semibold uppercase tracking-wide text-muted">
                                <th class="pb-2 pr-3">Animal</th>
                                <th class="pb-2 pr-3">Recorded on</th>
                                <th class="pb-2">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($drilldownRows as $record)
                                <tr class="border-t border-border/60">
                                    <td class="py-1.5 pr-3 text-ink">{{ $record->animal?->tag_number ?? $record->animal_id }}</td>
                                    <td class="py-1.5 pr-3 text-ink-2">{{ $record->recorded_on?->toDateString() }}</td>
                                    <td class="py-1.5 tnum text-ink-2">{{ number_format((float) $record->quantity, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-3 text-center text-muted">No production records in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </x-ui.panel>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        {{-- Procurement --}}
        <x-ui.panel title="Input Costs (Procurement)">
            @if (! $summary['procurement']['enabled'])
                <p class="py-6 text-center text-[13.5px] text-muted">Procurement is switched off for this business.</p>
            @else
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Purchase orders</span>
                        <span class="tnum font-semibold text-ink">{{ $summary['procurement']['order_count'] }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Received (total spend)</span>
                        <span class="tnum font-semibold text-ink">{{ Money::format($summary['procurement']['total_spend'], $currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Open order value</span>
                        <span class="tnum font-semibold text-ink">{{ Money::format($summary['procurement']['open_value'], $currency) }}</span>
                    </div>
                </div>

                <button type="button" wire:click="toggleDrilldown('procurement')"
                    class="mt-3 text-[13px] font-semibold text-brand hover:underline">
                    {{ $drilldown === 'procurement' ? 'Hide details' : 'View details' }}
                </button>

                @if ($drilldown === 'procurement')
                    <div class="mt-3 overflow-x-auto border-t border-border pt-3">
                    @if ($drilldownTotal > 200)
                        <p class="pt-3 text-[12px] text-muted">Showing 200 of {{ number_format($drilldownTotal) }} matching records.</p>
                    @endif
                        <table class="w-full text-left text-[13px]">
                            <thead>
                                <tr class="text-[12px] font-semibold uppercase tracking-wide text-muted">
                                    <th class="pb-2 pr-3">Number</th>
                                    <th class="pb-2 pr-3">Supplier</th>
                                    <th class="pb-2 pr-3">Status</th>
                                    <th class="pb-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($drilldownRows as $order)
                                    <tr class="border-t border-border/60">
                                        <td class="py-1.5 pr-3 text-ink">{{ $order->number }}</td>
                                        <td class="py-1.5 pr-3 text-ink-2">{{ $order->supplier?->name }}</td>
                                        <td class="py-1.5 pr-3 text-ink-2">{{ $order->status }}</td>
                                        <td class="py-1.5 tnum text-ink-2">{{ Money::format($order->total, $currency) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-3 text-center text-muted">No purchase orders in this range.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </x-ui.panel>

        {{-- Assets --}}
        <x-ui.panel title="Asset Maintenance Cost">
            @if (! $summary['assets']['enabled'])
                <p class="py-6 text-center text-[13.5px] text-muted">Fixed assets is switched off for this business.</p>
            @else
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Maintenance records</span>
                        <span class="tnum font-semibold text-ink">{{ $summary['assets']['maintenance_record_count'] }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Assets serviced</span>
                        <span class="tnum font-semibold text-ink">{{ $summary['assets']['assets_serviced'] }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Total maintenance cost</span>
                        <span class="tnum font-semibold text-ink">{{ Money::format($summary['assets']['maintenance_cost'], $currency) }}</span>
                    </div>
                </div>

                <button type="button" wire:click="toggleDrilldown('assets')"
                    class="mt-3 text-[13px] font-semibold text-brand hover:underline">
                    {{ $drilldown === 'assets' ? 'Hide details' : 'View details' }}
                </button>

                @if ($drilldown === 'assets')
                    <div class="mt-3 overflow-x-auto border-t border-border pt-3">
                    @if ($drilldownTotal > 200)
                        <p class="pt-3 text-[12px] text-muted">Showing 200 of {{ number_format($drilldownTotal) }} matching records.</p>
                    @endif
                        <table class="w-full text-left text-[13px]">
                            <thead>
                                <tr class="text-[12px] font-semibold uppercase tracking-wide text-muted">
                                    <th class="pb-2 pr-3">Asset</th>
                                    <th class="pb-2 pr-3">Performed on</th>
                                    <th class="pb-2">Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($drilldownRows as $record)
                                    <tr class="border-t border-border/60">
                                        <td class="py-1.5 pr-3 text-ink">{{ $record->asset?->name }}</td>
                                        <td class="py-1.5 pr-3 text-ink-2">{{ $record->performed_on?->toDateString() }}</td>
                                        <td class="py-1.5 tnum text-ink-2">{{ Money::format($record->cost, $currency) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-3 text-center text-muted">No maintenance records in this range.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </x-ui.panel>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        {{-- Cooperative --}}
        <x-ui.panel title="Loan Portfolio &amp; Contributions">
            @if (! $summary['cooperative']['enabled'])
                <p class="py-6 text-center text-[13.5px] text-muted">Cooperative is switched off for this business.</p>
            @else
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Loans</span>
                        <span class="tnum font-semibold text-ink">{{ $summary['cooperative']['loan_count'] }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Principal outstanding</span>
                        <span class="tnum font-semibold text-ink">{{ Money::format($summary['cooperative']['principal_outstanding'], $currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Principal disbursed</span>
                        <span class="tnum font-semibold text-ink">{{ Money::format($summary['cooperative']['principal_disbursed'], $currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-border pt-2 text-[13.5px]">
                        <span class="text-ink-2">Member contributions</span>
                        <span class="tnum font-semibold text-ink">{{ Money::format($summary['cooperative']['contribution_total'], $currency) }} ({{ $summary['cooperative']['contribution_count'] }})</span>
                    </div>
                </div>

                @if (! empty($summary['cooperative']['by_member']))
                    <div class="mt-4 overflow-x-auto border-t border-border pt-3">
                        <table class="w-full text-left text-[13px]">
                            <thead>
                                <tr class="text-[12px] font-semibold uppercase tracking-wide text-muted">
                                    <th class="pb-2 pr-3">Member</th>
                                    <th class="pb-2 pr-3">Loans</th>
                                    <th class="pb-2 pr-3">Outstanding</th>
                                    <th class="pb-2">Contributions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($summary['cooperative']['by_member'] as $row)
                                    <tr class="border-t border-border/60">
                                        <td class="py-1.5 pr-3 text-ink">{{ $row['member_name'] }}</td>
                                        <td class="py-1.5 pr-3 tnum text-ink-2">{{ $row['loan_count'] }}</td>
                                        <td class="py-1.5 pr-3 tnum text-ink-2">{{ Money::format($row['principal_outstanding'], $currency) }}</td>
                                        <td class="py-1.5 tnum text-ink-2">{{ Money::format($row['contribution_total'], $currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <button type="button" wire:click="toggleDrilldown('cooperative')"
                    class="mt-3 text-[13px] font-semibold text-brand hover:underline">
                    {{ $drilldown === 'cooperative' ? 'Hide details' : 'View details' }}
                </button>

                @if ($drilldown === 'cooperative')
                    <div class="mt-3 overflow-x-auto border-t border-border pt-3">
                    @if ($drilldownTotal > 200)
                        <p class="pt-3 text-[12px] text-muted">Showing 200 of {{ number_format($drilldownTotal) }} matching records.</p>
                    @endif
                        <table class="w-full text-left text-[13px]">
                            <thead>
                                <tr class="text-[12px] font-semibold uppercase tracking-wide text-muted">
                                    <th class="pb-2 pr-3">Member</th>
                                    <th class="pb-2 pr-3">Status</th>
                                    <th class="pb-2 pr-3">Principal</th>
                                    <th class="pb-2">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($drilldownRows as $loan)
                                    <tr class="border-t border-border/60">
                                        <td class="py-1.5 pr-3 text-ink">{{ $loan->member?->contact?->displayName() }}</td>
                                        <td class="py-1.5 pr-3 text-ink-2">{{ $loan->status }}</td>
                                        <td class="py-1.5 pr-3 tnum text-ink-2">{{ Money::format($loan->principal, $currency) }}</td>
                                        <td class="py-1.5 tnum text-ink-2">{{ Money::format($loan->balance, $currency) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-3 text-center text-muted">No loans in this range.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </x-ui.panel>

        {{-- Grants --}}
        <x-ui.panel title="Grant Fund Utilisation">
            @if (! $summary['grants']['enabled'])
                <p class="py-6 text-center text-[13.5px] text-muted">Grants is switched off for this business.</p>
            @else
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Projects</span>
                        <span class="tnum font-semibold text-ink">{{ $summary['grants']['project_count'] }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Received</span>
                        <span class="tnum font-semibold text-ink">{{ Money::format($summary['grants']['total_received'], $currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="text-ink-2">Spent</span>
                        <span class="tnum font-semibold text-ink">
                            {{ Money::format($summary['grants']['total_spent'], $currency) }}
                            @if ($summary['grants']['utilisation_pct'] !== null)
                                <span class="text-[12.5px] font-medium text-muted">({{ $summary['grants']['utilisation_pct'] }}%)</span>
                            @endif
                        </span>
                    </div>
                </div>

                <button type="button" wire:click="toggleDrilldown('grants')"
                    class="mt-3 text-[13px] font-semibold text-brand hover:underline">
                    {{ $drilldown === 'grants' ? 'Hide details' : 'View details' }}
                </button>

                @if ($drilldown === 'grants')
                    <div class="mt-3 overflow-x-auto border-t border-border pt-3">
                    @if ($drilldownTotal > 200)
                        <p class="pt-3 text-[12px] text-muted">Showing 200 of {{ number_format($drilldownTotal) }} matching records.</p>
                    @endif
                        <table class="w-full text-left text-[13px]">
                            <thead>
                                <tr class="text-[12px] font-semibold uppercase tracking-wide text-muted">
                                    <th class="pb-2 pr-3">Project</th>
                                    <th class="pb-2 pr-3">Type</th>
                                    <th class="pb-2 pr-3">Date</th>
                                    <th class="pb-2">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($drilldownRows as $transaction)
                                    <tr class="border-t border-border/60">
                                        <td class="py-1.5 pr-3 text-ink">{{ $transaction->project?->name }}</td>
                                        <td class="py-1.5 pr-3 text-ink-2">{{ $transaction->type }}</td>
                                        <td class="py-1.5 pr-3 text-ink-2">{{ $transaction->transaction_date?->toDateString() }}</td>
                                        <td class="py-1.5 tnum text-ink-2">{{ Money::format($transaction->amount, $currency) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-3 text-center text-muted">No grant transactions in this range.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </x-ui.panel>
    </div>

    {{-- Trend --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-ui.panel title="Input Costs, {{ $trendTitle }}">
            <x-ui.bar-chart :series="$toSeries($summary['trend'], 'procurement_cost')" height="h-[120px]" />
        </x-ui.panel>

        <x-ui.panel title="Harvest Volume, {{ $trendTitle }}">
            <x-ui.bar-chart :series="$toSeries($summary['trend'], 'harvest_volume')" height="h-[120px]" />
        </x-ui.panel>
    </div>
</div>
