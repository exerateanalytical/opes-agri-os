<?php

namespace App\Domain\Agri\Http\Controllers\Api\V1;

use App\Domain\Agri\Http\Requests\RecordHarvestRequest;
use App\Domain\Agri\Http\Requests\StoreCropCycleRequest;
use App\Domain\Agri\Http\Requests\UpdateCropCycleRequest;
use App\Domain\Agri\Http\Resources\CropCycleResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\CropCycle;
use App\Services\Agri\CropCyclePlanner;
use App\Services\Agri\HarvestRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CropCycleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CropCycle::class);

        $cycles = CropCycle::query()
            ->when($request->filled('field_id'), fn ($q) => $q->where('field_id', $request->string('field_id')))
            ->when($request->filled('season_id'), fn ($q) => $q->where('season_id', $request->string('season_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return CropCycleResource::collection($cycles);
    }

    public function show(CropCycle $cropCycle): CropCycleResource
    {
        $this->authorize('view', $cropCycle);

        return CropCycleResource::make($cropCycle);
    }

    public function store(StoreCropCycleRequest $request): JsonResponse
    {
        $cropCycle = CropCycle::create($request->validated() + [
            'status' => 'planned',
            'created_by' => $request->user()->id,
        ]);

        return CropCycleResource::make($cropCycle)->response()->setStatusCode(201);
    }

    public function update(UpdateCropCycleRequest $request, CropCycle $cropCycle, CropCyclePlanner $planner): CropCycleResource
    {
        $data = $request->validated();

        // A harvested/closed cycle is finished: routing through the same
        // guard CropCyclePlanner uses for its own transitions closes the
        // reopen path this plain update endpoint otherwise leaves open.
        if (array_intersect(['status', 'growth_stage'], array_keys($data)) !== []) {
            $planner->guardNotFinished($cropCycle);
        }

        $cropCycle->update($data);

        return CropCycleResource::make($cropCycle);
    }

    public function harvest(RecordHarvestRequest $request, CropCycle $cropCycle, HarvestRecorder $recorder): CropCycleResource
    {
        $harvested = $recorder->record(
            $cropCycle,
            $request->user(),
            (float) $request->validated('quantity'),
            $request->validated('unit_cost') !== null ? (float) $request->validated('unit_cost') : null,
            [
                'batch_number' => $request->validated('batch_number'),
                'expires_on' => $request->validated('expires_on'),
            ],
        );

        return CropCycleResource::make($harvested);
    }
}
