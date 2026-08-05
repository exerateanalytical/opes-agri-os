<?php

namespace App\Domain\Agri\Http\Controllers\Api\V1;

use App\Domain\Agri\Http\Requests\StoreFieldRequest;
use App\Domain\Agri\Http\Requests\UpdateFieldRequest;
use App\Domain\Agri\Http\Resources\FieldResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Field;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FieldController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Field::class);

        $fields = Field::query()
            ->when($request->filled('farm_id'), fn ($q) => $q->where('farm_id', $request->string('farm_id')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return FieldResource::collection($fields);
    }

    public function show(Field $field): FieldResource
    {
        $this->authorize('view', $field);

        return FieldResource::make($field);
    }

    public function store(StoreFieldRequest $request): JsonResponse
    {
        $field = Field::create($request->validated());

        return FieldResource::make($field)->response()->setStatusCode(201);
    }

    public function update(UpdateFieldRequest $request, Field $field): FieldResource
    {
        $field->update($request->validated());

        return FieldResource::make($field);
    }

    public function destroy(Field $field): Response
    {
        $this->authorize('delete', $field);

        $field->delete();

        return response()->noContent();
    }
}
