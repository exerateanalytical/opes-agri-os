@php
    use App\Support\Money;

    // Turn a plain [label, value] trend array into the {label, value, highlight}
    // shape x-ui.bar-chart expects — the same peak-highlight convention Reports
    // uses for its own series.
    $toSeries = function (array $points, string $key) {
        $series = collect($points)->map(fn (array $p) => ['label' => $p['label'], 'value' => (float) $p[$key]]);
        $peak = $series->max('value');

        return $series->map(fn (array $p) => [...$p, 'highlight' => $peak > 0 && $p['value'] === $peak])->all();
    };
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div>
        <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Analytics</h1>
        <p class="mt-1 text-[14.5px] text-muted">A cross-module operations picture — crops, livestock, procurement, assets, cooperative and grants.</p>
    </div>

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
                    <p class="text-[12.5px] font-medium text-muted">Production records (6mo)</p>
                    <p class="tnum text-[18px] font-bold text-ink">{{ $summary['livestock']['production_records'] }}</p>
                </div>
                <div>
                    <p class="text-[12.5px] font-medium text-muted">Production volume (6mo)</p>
                    <p class="tnum text-[18px] font-bold text-ink">{{ number_format($summary['livestock']['production_volume'], 2) }}</p>
                </div>
            </div>
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
            @endif
        </x-ui.panel>
    </div>

    {{-- Trend --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-ui.panel title="Input Costs, Last 6 Months">
            <x-ui.bar-chart :series="$toSeries($summary['trend'], 'procurement_cost')" height="h-[120px]" />
        </x-ui.panel>

        <x-ui.panel title="Harvest Volume, Last 6 Months">
            <x-ui.bar-chart :series="$toSeries($summary['trend'], 'harvest_volume')" height="h-[120px]" />
        </x-ui.panel>
    </div>
</div>
