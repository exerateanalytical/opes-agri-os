<?php

namespace App\Domain\Agri\Http\Controllers\Api\V1;

use App\Domain\Agri\Http\Requests\StoreSeasonRequest;
use App\Domain\Agri\Http\Requests\UpdateSeasonRequest;
use App\Domain\Agri\Http\Resources\SeasonResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SeasonController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Season::class);

        $seasons = Season::query()
            ->orderByDesc('starts_on')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return SeasonResource::collection($seasons);
    }

    public function show(Season $season): SeasonResource
    {
        $this->authorize('view', $season);

        return SeasonResource::make($season);
    }

    public function store(StoreSeasonRequest $request): JsonResponse
    {
        $season = Season::create($request->validated());

        return SeasonResource::make($season)->response()->setStatusCode(201);
    }

    public function update(UpdateSeasonRequest $request, Season $season): SeasonResource
    {
        $season->update($request->validated());

        return SeasonResource::make($season);
    }

    public function destroy(Season $season): Response
    {
        $this->authorize('delete', $season);

        $season->delete();

        return response()->noContent();
    }
}
