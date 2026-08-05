<?php

namespace App\Domain\Analytics\Services;

use App\Models\Animal;
use App\Models\AnimalProductionRecord;
use App\Models\AssetMaintenanceRecord;
use App\Models\Company;
use App\Models\CropCycle;
use App\Models\GrantProject;
use App\Models\Loan;
use App\Models\MemberContribution;
use App\Models\PurchaseOrder;
use App\Support\Modules;
use Carbon\CarbonImmutable;

/**
 * Cross-module operations analytics — read-only aggregation over data every
 * other V1–V4 module already writes. Nothing here persists anything; it is
 * the same shape Phase 3b (Traceability) took over StockMovement, applied to
 * six modules instead of one.
 *
 * Each section is independently gated by whether its source module is on for
 * the company: a business with Crops switched off gets an empty crops
 * section rather than a query error or a leaked count, the same "degrade
 * gracefully" rule the roadmap sets for every module toggle.
 *
 * `from`/`to` are optional — every method defaults to the same all-time (or,
 * for the trend row, fixed six-month) behaviour it always had when they are
 * omitted, so the fixed 6-month view stays the default and every existing
 * caller keeps working unchanged. `groupBy` similarly defaults to the plain
 * company-wide total; passing 'farm' (crops) or 'member' (cooperative) adds
 * a breakdown array alongside the totals rather than replacing them.
 */
class AnalyticsSummaryService
{
    /** How far back the trend series looks when no explicit range is given. */
    protected const TREND_MONTHS = 6;

    /** @return array<string, mixed> */
    public function summary(Company $company, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, ?string $cropsGroupBy = null, ?string $cooperativeGroupBy = null): array
    {
        return [
            'crops' => $this->crops($company, $from, $to, $cropsGroupBy),
            'livestock' => $this->livestock($company, $from, $to),
            'procurement' => $this->procurement($company, $from, $to),
            'assets' => $this->assets($company, $from, $to),
            'cooperative' => $this->cooperative($company, $from, $to, $cooperativeGroupBy),
            'grants' => $this->grants($company, $from, $to),
            'trend' => $this->trend($company, $from, $to),
            'range' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function crops(Company $company, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, ?string $groupBy = null): array
    {
        if (! Modules::enabled($company, 'crops')) {
            return ['enabled' => false];
        }

        $query = CropCycle::query()->with($groupBy === 'farm' ? ['field.farm'] : []);
        $this->applyCropDateRange($query, $from, $to);

        $cycles = $query->get(['id', 'field_id', 'status', 'planned_yield_qty', 'actual_yield_qty', 'planned_planting_date', 'actual_harvest_date']);
        $harvested = $cycles->where('status', 'harvested');

        $plannedTotal = (float) $cycles->sum('planned_yield_qty');
        $actualTotal = (float) $harvested->sum('actual_yield_qty');

        $result = [
            'enabled' => true,
            'cycle_count' => $cycles->count(),
            'harvested_count' => $harvested->count(),
            'planned_yield' => $plannedTotal,
            'actual_yield' => $actualTotal,
            // Null rather than 0% when nothing has been harvested yet — a
            // ratio needs a denominator that actually happened.
            'yield_attainment_pct' => $plannedTotal > 0 && $harvested->isNotEmpty()
                ? round($actualTotal / $cycles->where('status', 'harvested')->sum('planned_yield_qty') * 100, 1)
                : null,
            'by_status' => $cycles->countBy('status')->all(),
        ];

        if ($groupBy === 'farm') {
            $result['by_farm'] = $cycles
                ->groupBy(fn (CropCycle $c) => $c->field?->farm_id ?? 'unassigned')
                ->map(function ($group) {
                    $farm = $group->first()->field?->farm;
                    $groupHarvested = $group->where('status', 'harvested');

                    return [
                        'farm_id' => $farm?->id,
                        'farm_name' => $farm?->name ?? 'Unassigned',
                        'cycle_count' => $group->count(),
                        'planned_yield' => (float) $group->sum('planned_yield_qty'),
                        'actual_yield' => (float) $groupHarvested->sum('actual_yield_qty'),
                    ];
                })
                ->values()
                ->all();
        }

        return $result;
    }

    protected function applyCropDateRange(mixed $query, ?CarbonImmutable $from, ?CarbonImmutable $to): void
    {
        if (! $from || ! $to) {
            return;
        }

        // A cycle counts toward a range if either its planting or its
        // harvest fell inside it — a cycle planted in range but not yet
        // harvested should still show up.
        $query->where(function ($q) use ($from, $to) {
            $q->whereBetween('planned_planting_date', [$from->toDateString(), $to->toDateString()])
                ->orWhereBetween('actual_harvest_date', [$from->toDateString(), $to->toDateString()]);
        });
    }

    /** @return array<string, mixed> */
    protected function livestock(Company $company, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        if (! Modules::enabled($company, 'livestock')) {
            return ['enabled' => false];
        }

        $animals = Animal::query()->get(['status']);

        // Preserves the original fixed-window math exactly (now minus six
        // whole months) rather than the trend row's month-bucket-aligned
        // five-month-back start — this section was never bucketed by month,
        // so there is no reason to change its default when from/to are
        // omitted.
        [$since, $until] = ($from && $to)
            ? [$from, $to]
            : [CarbonImmutable::now()->subMonths(self::TREND_MONTHS)->startOfMonth(), CarbonImmutable::now()];

        $production = AnimalProductionRecord::query()
            ->where('recorded_on', '>=', $since->toDateString())
            ->where('recorded_on', '<=', $until->toDateString())
            ->get(['quantity', 'recorded_on']);

        return [
            'enabled' => true,
            'animal_count' => $animals->count(),
            'by_status' => $animals->countBy('status')->all(),
            'production_records' => $production->count(),
            'production_volume' => (float) $production->sum('quantity'),
        ];
    }

    /** @return array<string, mixed> */
    protected function procurement(Company $company, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        if (! Modules::enabled($company, 'procurement')) {
            return ['enabled' => false];
        }

        $query = PurchaseOrder::query();
        if ($from && $to) {
            $query->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);
        }

        $orders = $query->get(['status', 'total']);
        $received = $orders->where('status', 'received');

        return [
            'enabled' => true,
            'order_count' => $orders->count(),
            'by_status' => $orders->countBy('status')->all(),
            'total_spend' => (float) $received->sum('total'),
            'open_value' => (float) $orders->whereIn('status', ['draft', 'issued'])->sum('total'),
        ];
    }

    /** @return array<string, mixed> */
    protected function assets(Company $company, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        if (! Modules::enabled($company, 'assets')) {
            return ['enabled' => false];
        }

        $query = AssetMaintenanceRecord::query();
        if ($from && $to) {
            $query->whereBetween('performed_on', [$from->toDateString(), $to->toDateString()]);
        }

        $records = $query->get(['cost', 'fixed_asset_id']);

        return [
            'enabled' => true,
            'maintenance_record_count' => $records->count(),
            'maintenance_cost' => (float) $records->sum('cost'),
            'assets_serviced' => $records->pluck('fixed_asset_id')->unique()->count(),
        ];
    }

    /** @return array<string, mixed> */
    protected function cooperative(Company $company, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, ?string $groupBy = null): array
    {
        if (! Modules::enabled($company, 'cooperative')) {
            return ['enabled' => false];
        }

        $loanQuery = Loan::query()->with($groupBy === 'member' ? ['member.contact'] : []);
        if ($from && $to) {
            $loanQuery->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
        }
        $loans = $loanQuery->get(['id', 'cooperative_member_id', 'status', 'principal', 'balance']);
        $active = $loans->whereIn('status', ['active', 'pending']);

        $contributionQuery = MemberContribution::query()->with($groupBy === 'member' ? ['member.contact'] : []);
        if ($from && $to) {
            $contributionQuery->whereBetween('contributed_on', [$from->toDateString(), $to->toDateString()]);
        }
        $contributions = $contributionQuery->get(['id', 'cooperative_member_id', 'amount']);

        $result = [
            'enabled' => true,
            'loan_count' => $loans->count(),
            'by_status' => $loans->countBy('status')->all(),
            'principal_outstanding' => (float) $active->sum('balance'),
            'principal_disbursed' => (float) $loans->whereIn('status', ['active', 'closed', 'defaulted'])->sum('principal'),
            'contribution_total' => (float) $contributions->sum('amount'),
            'contribution_count' => $contributions->count(),
        ];

        if ($groupBy === 'member') {
            $result['by_member'] = $loans
                ->pluck('cooperative_member_id')
                ->concat($contributions->pluck('cooperative_member_id'))
                ->unique()
                ->map(function ($memberId) use ($loans, $contributions) {
                    $memberLoans = $loans->where('cooperative_member_id', $memberId);
                    $memberContributions = $contributions->where('cooperative_member_id', $memberId);
                    $member = $memberLoans->first()?->member ?? $memberContributions->first()?->member;

                    return [
                        'member_id' => $memberId,
                        'member_name' => $member?->contact?->displayName() ?? $member?->membership_number ?? 'Unknown member',
                        'loan_count' => $memberLoans->count(),
                        'principal_outstanding' => (float) $memberLoans->whereIn('status', ['active', 'pending'])->sum('balance'),
                        'contribution_total' => (float) $memberContributions->sum('amount'),
                    ];
                })
                ->values()
                ->all();
        }

        return $result;
    }

    /** @return array<string, mixed> */
    protected function grants(Company $company, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        if (! Modules::enabled($company, 'grants')) {
            return ['enabled' => false];
        }

        // Project totals stay cumulative to-date figures — a grant's
        // committed/received/spent amounts don't have a meaningful "in this
        // date range" slice at the project level, only its transactions do
        // (see the drill-down list, which does filter by date).
        $projects = GrantProject::query()->get(['status', 'total_amount', 'received_amount', 'spent_amount']);

        $received = (float) $projects->sum('received_amount');
        $spent = (float) $projects->sum('spent_amount');

        return [
            'enabled' => true,
            'project_count' => $projects->count(),
            'by_status' => $projects->countBy('status')->all(),
            'total_committed' => (float) $projects->sum('total_amount'),
            'total_received' => $received,
            'total_spent' => $spent,
            // Null rather than 0% when nothing has arrived yet, same
            // reasoning as crops' yield_attainment_pct.
            'utilisation_pct' => $received > 0 ? round($spent / $received * 100, 1) : null,
        ];
    }

    /**
     * Input costs vs harvest volume, month by month. Defaults to the fixed
     * six-month lookback window when no range is given — the roadmap's
     * original scope cut — but re-buckets over an explicit from/to when one
     * is supplied.
     *
     * @return array<int, array{label: string, procurement_cost: float, harvest_volume: float}>
     */
    protected function trend(Company $company, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        [$since, $until] = $this->rangeOrDefault($from, $to, startOfMonth: true);
        $months = max(1, $since->diffInMonths($until->startOfMonth()) + 1);

        $procurementByMonth = Modules::enabled($company, 'procurement')
            ? PurchaseOrder::query()
                ->where('status', 'received')
                ->where('order_date', '>=', $since->toDateString())
                ->where('order_date', '<=', $until->toDateString())
                ->get(['order_date', 'total'])
                ->groupBy(fn ($o) => $o->order_date?->format('Y-m'))
            : collect();

        $harvestByMonth = Modules::enabled($company, 'crops')
            ? CropCycle::query()
                ->where('status', 'harvested')
                ->where('actual_harvest_date', '>=', $since->toDateString())
                ->where('actual_harvest_date', '<=', $until->toDateString())
                ->get(['actual_harvest_date', 'actual_yield_qty'])
                ->groupBy(fn ($c) => $c->actual_harvest_date?->format('Y-m'))
            : collect();

        return collect(range(0, $months - 1))
            ->map(function (int $i) use ($since, $procurementByMonth, $harvestByMonth) {
                $month = $since->addMonths($i);
                $key = $month->format('Y-m');

                return [
                    'label' => $month->format('M'),
                    'procurement_cost' => (float) ($procurementByMonth->get($key)?->sum('total') ?? 0),
                    'harvest_volume' => (float) ($harvestByMonth->get($key)?->sum('actual_yield_qty') ?? 0),
                ];
            })
            ->all();
    }

    /**
     * The fixed six-month window when no explicit range is given, otherwise
     * the caller's own from/to. `startOfMonth` bucket-aligns the fallback
     * the trend row needs; other sections pass it false and just get from/to
     * (or "now, twelve months back" as a generous all-time-ish default is
     * intentionally NOT applied there — they stay unfiltered unless both
     * from and to are supplied, see each method above).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function rangeOrDefault(?CarbonImmutable $from, ?CarbonImmutable $to, bool $startOfMonth = false): array
    {
        if ($from && $to) {
            return [$startOfMonth ? $from->startOfMonth() : $from, $to];
        }

        $now = CarbonImmutable::now();
        $since = $now->subMonths(self::TREND_MONTHS - 1);

        return [$startOfMonth ? $since->startOfMonth() : $since->startOfMonth(), $now];
    }
}
