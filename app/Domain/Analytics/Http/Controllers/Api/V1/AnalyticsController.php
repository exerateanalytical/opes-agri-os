<?php

namespace App\Domain\Analytics\Http\Controllers\Api\V1;

use App\Domain\Analytics\Services\AnalyticsSummaryService;
use App\Http\Controllers\Api\V1\Controller;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    /**
     * The whole cross-module dashboard in one call — an aggregation, not a
     * resource collection, so a plain JSON shape rather than an API Resource
     * fits (there is no single Eloquent model behind it, the same reasoning
     * that keeps Reports off the API surface entirely; this endpoint exists
     * because Analytics, unlike Reports, is meant to be consumed
     * programmatically too).
     */
    public function dashboard(AnalyticsSummaryService $service): JsonResponse
    {
        $this->authorize('analytics.view');

        $company = app(CurrentCompany::class)->get();

        return response()->json(['data' => $service->summary($company)]);
    }
}
