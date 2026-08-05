<?php

namespace App\Domain\Agri\Http\Controllers\Api\V1;

use App\Domain\Agri\Http\Requests\StoreFarmRequest;
use App\Domain\Agri\Http\Requests\UpdateFarmRequest;
use App\Domain\Agri\Http\Resources\FarmResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FarmController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Farm::class);

        $farms = Farm::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->withCount('fields')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return FarmResource::collection($farms);
    }

    public function show(Farm $farm): FarmResource
    {
        $this->authorize('view', $farm);

        return FarmResource::make($farm->loadCount('fields'));
    }

    public function store(StoreFarmRequest $request): JsonResponse
    {
        $farm = Farm::create($request->validated() + ['created_by' => $request->user()->id]);

        return FarmResource::make($farm)->response()->setStatusCode(201);
    }

    public function update(UpdateFarmRequest $request, Farm $farm): FarmResource
    {
        $farm->update($request->validated());

        return FarmResource::make($farm);
    }

    public function destroy(Farm $farm): Response
    {
        $this->authorize('delete', $farm);

        $farm->delete();

        return response()->noContent();
    }
}
