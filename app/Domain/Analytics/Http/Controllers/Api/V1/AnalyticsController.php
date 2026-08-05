<?php

namespace App\Domain\Analytics\Http\Controllers\Api\V1;

use App\Domain\Analytics\Services\AnalyticsSummaryService;
use App\Http\Controllers\Api\V1\Controller;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * The whole cross-module dashboard in one call — an aggregation, not a
     * resource collection, so a plain JSON shape rather than an API Resource
     * fits (there is no single Eloquent model behind it, the same reasoning
     * that keeps Reports off the API surface entirely; this endpoint exists
     * because Analytics, unlike Reports, is meant to be consumed
     * programmatically too).
     *
     * `from`/`to` are optional query params — the fixed 6-month view stays
     * the default when neither is passed. `crops_group_by=farm` and
     * `cooperative_group_by=member` add a breakdown array to those two
     * sections; any other value is rejected the same way an out-of-range
     * date pair is.
     */
    public function dashboard(Request $request, AnalyticsSummaryService $service): JsonResponse
    {
        $this->authorize('analytics.view');

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'crops_group_by' => ['nullable', 'in:farm'],
            'cooperative_group_by' => ['nullable', 'in:member'],
        ]);

        $company = app(CurrentCompany::class)->get();

        [$from, $to] = $this->range($validated);

        return response()->json(['data' => $service->summary(
            $company,
            $from,
            $to,
            $validated['crops_group_by'] ?? null,
            $validated['cooperative_group_by'] ?? null,
        )]);
    }

    /** @param array<string, mixed> $validated */
    protected function range(array $validated): array
    {
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'])->endOfDay() : null;

        // Both or neither — a single bound with no partner isn't a range,
        // it's an accident, so it's treated as "no range given" rather than
        // silently building an open-ended query.
        if (! $from || ! $to) {
            return [null, null];
        }

        return [$from, $to];
    }
}
