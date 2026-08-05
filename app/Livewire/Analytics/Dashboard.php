<?php

namespace App\Livewire\Analytics;

use App\Domain\Analytics\Services\AnalyticsSummaryService;
use App\Models\AnimalProductionRecord;
use App\Models\AssetMaintenanceRecord;
use App\Models\CropCycle;
use App\Models\GrantTransaction;
use App\Models\Loan;
use App\Models\PurchaseOrder;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One screen. Every section reads all-time (or, for the trend row, the fixed
 * lookback window AnalyticsSummaryService uses) by default — the same
 * standing operations picture the roadmap describes — but `from`/`to` let a
 * user narrow every section to a custom range, `cropsGroupBy`/
 * `cooperativeGroupBy` add a per-farm/per-member breakdown, and `drilldown`
 * expands one section into the underlying records behind its numbers.
 */
class Dashboard extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $cropsGroupBy = '';

    #[Url]
    public string $cooperativeGroupBy = '';

    /** Which section, if any, is expanded into its underlying records. */
    #[Url]
    public string $drilldown = '';

    public function mount(): void
    {
        $this->authorize('analytics.view');
    }

    public function toggleDrilldown(string $section): void
    {
        $this->drilldown = $this->drilldown === $section ? '' : $section;
    }

    public function clearRange(): void
    {
        $this->from = '';
        $this->to = '';
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    protected function range(): array
    {
        if ($this->from === '' || $this->to === '') {
            return [null, null];
        }

        try {
            return [
                CarbonImmutable::parse($this->from)->startOfDay(),
                CarbonImmutable::parse($this->to)->endOfDay(),
            ];
        } catch (\Exception) {
            return [null, null];
        }
    }

    /**
     * The underlying records behind the currently expanded section, scoped
     * to the active date range — a minimal list built here rather than
     * linking out, since no existing module list view accepts an arbitrary
     * date range or reads company-wide across the fields this needs.
     */
    protected function drilldownRows(): Collection
    {
        [$from, $to] = $this->range();

        return match ($this->drilldown) {
            'crops' => CropCycle::query()
                ->with('field.farm')
                ->when($from && $to, fn ($q) => $q->where(function ($q) use ($from, $to) {
                    $q->whereBetween('planned_planting_date', [$from->toDateString(), $to->toDateString()])
                        ->orWhereBetween('actual_harvest_date', [$from->toDateString(), $to->toDateString()]);
                }))
                ->latest('created_at')
                ->limit(200)
                ->get(),
            'livestock' => AnimalProductionRecord::query()
                ->with('animal')
                ->when($from && $to,
                    fn ($q) => $q->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()]),
                    fn ($q) => $q->where('recorded_on', '>=', CarbonImmutable::now()->subMonths(6)->startOfMonth()->toDateString()),
                )
                ->latest('recorded_on')
                ->limit(200)
                ->get(),
            'procurement' => PurchaseOrder::query()
                ->with('supplier')
                ->when($from && $to, fn ($q) => $q->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]))
                ->latest('order_date')
                ->limit(200)
                ->get(),
            'assets' => AssetMaintenanceRecord::query()
                ->with('asset')
                ->when($from && $to, fn ($q) => $q->whereBetween('performed_on', [$from->toDateString(), $to->toDateString()]))
                ->latest('performed_on')
                ->limit(200)
                ->get(),
            'cooperative' => Loan::query()
                ->with('member.contact')
                ->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
                ->latest('created_at')
                ->limit(200)
                ->get(),
            'grants' => GrantTransaction::query()
                ->with('project')
                ->when($from && $to, fn ($q) => $q->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()]))
                ->latest('transaction_date')
                ->limit(200)
                ->get(),
            default => collect(),
        };
    }

    /**
     * CSV export of the current dashboard view — respects the active
     * date-range and breakdown filters, one combined file rather than one
     * per section, following the Reports `exportCsv` pattern.
     */
    public function exportCsv(): StreamedResponse
    {
        $this->authorize('analytics.export');

        $company = app(CurrentCompany::class)->get();
        [$from, $to] = $this->range();

        $summary = app(AnalyticsSummaryService::class)->summary(
            $company,
            $from,
            $to,
            $this->cropsGroupBy ?: null,
            $this->cooperativeGroupBy ?: null,
        );

        $filename = 'analytics-'.($this->from ?: 'alltime').'-to-'.($this->to ?: 'now').'.csv';

        return response()->streamDownload(function () use ($summary) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Section', 'Metric', 'Value']);

            foreach ($summary as $section => $data) {
                if ($section === 'range' || ! is_array($data) || ($data['enabled'] ?? true) === false) {
                    continue;
                }

                foreach ($data as $metric => $value) {
                    if (is_array($value)) {
                        continue; // by_status / by_farm / by_member — flattened separately below.
                    }
                    fputcsv($out, [$section, $metric, $value]);
                }
            }

            foreach (['by_farm' => 'crops', 'by_member' => 'cooperative'] as $key => $section) {
                foreach ($summary[$section][$key] ?? [] as $row) {
                    fputcsv($out, [$section, $key, json_encode($row)]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render(): View
    {
        $company = app(CurrentCompany::class)->get();
        [$from, $to] = $this->range();

        return view('livewire.analytics.dashboard', [
            'summary' => app(AnalyticsSummaryService::class)->summary(
                $company,
                $from,
                $to,
                $this->cropsGroupBy ?: null,
                $this->cooperativeGroupBy ?: null,
            ),
            'currency' => $company?->currency ?? 'USD',
            'drilldownRows' => $this->drilldown !== '' ? $this->drilldownRows() : collect(),
        ])->layout('components.layouts.app', ['title' => 'Analytics', 'active' => 'analytics']);
    }
}
