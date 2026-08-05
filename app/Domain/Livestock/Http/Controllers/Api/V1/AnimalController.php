<?php

namespace App\Domain\Livestock\Http\Controllers\Api\V1;

use App\Domain\Livestock\Http\Requests\RecordHealthRequest;
use App\Domain\Livestock\Http\Requests\RecordProductionRequest;
use App\Domain\Livestock\Http\Requests\StoreAnimalRequest;
use App\Domain\Livestock\Http\Requests\UpdateAnimalRequest;
use App\Domain\Livestock\Http\Resources\AnimalHealthRecordResource;
use App\Domain\Livestock\Http\Resources\AnimalProductionRecordResource;
use App\Domain\Livestock\Http\Resources\AnimalResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Animal;
use App\Models\Item;
use App\Services\Livestock\ProductionRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnimalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Animal::class);

        $animals = Animal::query()
            ->when($request->filled('farm_id'), fn ($q) => $q->where('farm_id', $request->string('farm_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('species'), fn ($q) => $q->where('species', $request->string('species')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return AnimalResource::collection($animals);
    }

    public function show(Animal $animal): AnimalResource
    {
        $this->authorize('view', $animal);

        return AnimalResource::make($animal->load(['healthRecords', 'productionRecords']));
    }

    public function store(StoreAnimalRequest $request): JsonResponse
    {
        $animal = Animal::create($request->validated() + [
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return AnimalResource::make($animal)->response()->setStatusCode(201);
    }

    public function update(UpdateAnimalRequest $request, Animal $animal): AnimalResource
    {
        $animal->update($request->validated());

        return AnimalResource::make($animal);
    }

    public function destroy(Animal $animal): JsonResponse
    {
        $this->authorize('delete', $animal);

        $animal->delete();

        return response()->json(status: 204);
    }

    public function recordHealth(RecordHealthRequest $request, Animal $animal): AnimalHealthRecordResource
    {
        $record = $animal->healthRecords()->create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);

        return AnimalHealthRecordResource::make($record);
    }

    public function recordProduction(RecordProductionRequest $request, Animal $animal, ProductionRecorder $recorder): AnimalProductionRecordResource
    {
        $item = $request->validated('item_id') ? Item::findOrFail($request->validated('item_id')) : null;

        $record = $recorder->record(
            $animal,
            $request->user(),
            $item,
            (float) $request->validated('quantity'),
            $request->validated('recorded_on'),
            [
                'notes' => $request->validated('notes'),
                'unit_cost' => $request->validated('unit_cost') !== null ? (float) $request->validated('unit_cost') : null,
            ],
        );

        return AnimalProductionRecordResource::make($record);
    }
}
