<?php

namespace App\Domain\Livestock\Http\Controllers\Api\V1;

use App\Domain\Livestock\Http\Requests\AdjustAnimalBatchCountRequest;
use App\Domain\Livestock\Http\Requests\StoreAnimalBatchRequest;
use App\Domain\Livestock\Http\Requests\UpdateAnimalBatchRequest;
use App\Domain\Livestock\Http\Resources\AnimalBatchResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\AnimalBatch;
use App\Services\Livestock\BatchCountAdjuster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnimalBatchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AnimalBatch::class);

        $batches = AnimalBatch::query()
            ->when($request->filled('farm_id'), fn ($q) => $q->where('farm_id', $request->string('farm_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return AnimalBatchResource::collection($batches);
    }

    public function show(AnimalBatch $animalBatch): AnimalBatchResource
    {
        $this->authorize('view', $animalBatch);

        return AnimalBatchResource::make($animalBatch);
    }

    public function store(StoreAnimalBatchRequest $request): JsonResponse
    {
        $data = $request->validated();

        $batch = AnimalBatch::create($data + [
            'current_count' => $data['initial_count'],
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return AnimalBatchResource::make($batch)->response()->setStatusCode(201);
    }

    public function update(UpdateAnimalBatchRequest $request, AnimalBatch $animalBatch): AnimalBatchResource
    {
        $animalBatch->update($request->validated());

        return AnimalBatchResource::make($animalBatch);
    }

    public function destroy(AnimalBatch $animalBatch): JsonResponse
    {
        $this->authorize('delete', $animalBatch);

        $animalBatch->delete();

        return response()->json(status: 204);
    }

    public function adjustCount(AdjustAnimalBatchCountRequest $request, AnimalBatch $animalBatch, BatchCountAdjuster $adjuster): AnimalBatchResource
    {
        $adjusted = $adjuster->adjust($animalBatch, (int) $request->validated('change'));

        return AnimalBatchResource::make($adjusted);
    }
}
