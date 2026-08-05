<?php

namespace App\Livewire\Analytics;

use App\Domain\Analytics\Services\AnalyticsSummaryService;
use App\Support\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * One screen, no filters — every section reads all-time (or, for the trend
 * row, the fixed lookback window AnalyticsSummaryService uses) rather than a
 * period a user picks, unlike Reports. See the roadmap for why: this is a
 * standing operations picture across modules, not a period close-out.
 */
class Dashboard extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('analytics.view');
    }

    public function render(): View
    {
        $company = app(CurrentCompany::class)->get();

        return view('livewire.analytics.dashboard', [
            'summary' => app(AnalyticsSummaryService::class)->summary($company),
            'currency' => $company?->currency ?? 'USD',
        ])->layout('components.layouts.app', ['title' => 'Analytics', 'active' => 'analytics']);
    }
}
