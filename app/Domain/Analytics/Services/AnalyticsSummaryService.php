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
 */
class AnalyticsSummaryService
{
    /** How far back the trend series look — a fixed window, not a picker. See roadmap for the scope cut. */
    protected const TREND_MONTHS = 6;

    /** @return array<string, mixed> */
    public function summary(Company $company): array
    {
        return [
            'crops' => $this->crops($company),
            'livestock' => $this->livestock($company),
            'procurement' => $this->procurement($company),
            'assets' => $this->assets($company),
            'cooperative' => $this->cooperative($company),
            'grants' => $this->grants($company),
            'trend' => $this->trend($company),
        ];
    }

    /** @return array<string, mixed> */
    protected function crops(Company $company): array
    {
        if (! Modules::enabled($company, 'crops')) {
            return ['enabled' => false];
        }

        $cycles = CropCycle::query()->get(['status', 'planned_yield_qty', 'actual_yield_qty']);
        $harvested = $cycles->where('status', 'harvested');

        $plannedTotal = (float) $cycles->sum('planned_yield_qty');
        $actualTotal = (float) $harvested->sum('actual_yield_qty');

        return [
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
    }

    /** @return array<string, mixed> */
    protected function livestock(Company $company): array
    {
        if (! Modules::enabled($company, 'livestock')) {
            return ['enabled' => false];
        }

        $animals = Animal::query()->get(['status']);

        $since = CarbonImmutable::now()->subMonths(self::TREND_MONTHS)->startOfMonth();
        $production = AnimalProductionRecord::query()
            ->where('recorded_on', '>=', $since)
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
    protected function procurement(Company $company): array
    {
        if (! Modules::enabled($company, 'procurement')) {
            return ['enabled' => false];
        }

        $orders = PurchaseOrder::query()->get(['status', 'total']);
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
    protected function assets(Company $company): array
    {
        if (! Modules::enabled($company, 'assets')) {
            return ['enabled' => false];
        }

        $records = AssetMaintenanceRecord::query()->get(['cost', 'fixed_asset_id']);

        return [
            'enabled' => true,
            'maintenance_record_count' => $records->count(),
            'maintenance_cost' => (float) $records->sum('cost'),
            'assets_serviced' => $records->pluck('fixed_asset_id')->unique()->count(),
        ];
    }

    /** @return array<string, mixed> */
    protected function cooperative(Company $company): array
    {
        if (! Modules::enabled($company, 'cooperative')) {
            return ['enabled' => false];
        }

        $loans = Loan::query()->get(['status', 'principal', 'balance']);
        $active = $loans->whereIn('status', ['active', 'pending']);

        $contributions = MemberContribution::query()->get(['amount']);

        return [
            'enabled' => true,
            'loan_count' => $loans->count(),
            'by_status' => $loans->countBy('status')->all(),
            'principal_outstanding' => (float) $active->sum('balance'),
            'principal_disbursed' => (float) $loans->whereIn('status', ['active', 'closed', 'defaulted'])->sum('principal'),
            'contribution_total' => (float) $contributions->sum('amount'),
            'contribution_count' => $contributions->count(),
        ];
    }

    /** @return array<string, mixed> */
    protected function grants(Company $company): array
    {
        if (! Modules::enabled($company, 'grants')) {
            return ['enabled' => false];
        }

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
     * Input costs vs harvest volume, month by month, over a fixed lookback
     * window. The one period-over-period view this dashboard ships — see the
     * roadmap for why it stops at this and doesn't grow a date-range picker.
     *
     * @return array<int, array{label: string, procurement_cost: float, harvest_volume: float}>
     */
    protected function trend(Company $company): array
    {
        $now = CarbonImmutable::now();
        $since = $now->subMonths(self::TREND_MONTHS - 1)->startOfMonth();

        $procurementByMonth = Modules::enabled($company, 'procurement')
            ? PurchaseOrder::query()
                ->where('status', 'received')
                ->where('order_date', '>=', $since)
                ->get(['order_date', 'total'])
                ->groupBy(fn ($o) => $o->order_date?->format('Y-m'))
            : collect();

        $harvestByMonth = Modules::enabled($company, 'crops')
            ? CropCycle::query()
                ->where('status', 'harvested')
                ->where('actual_harvest_date', '>=', $since)
                ->get(['actual_harvest_date', 'actual_yield_qty'])
                ->groupBy(fn ($c) => $c->actual_harvest_date?->format('Y-m'))
            : collect();

        return collect(range(0, self::TREND_MONTHS - 1))
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
}
