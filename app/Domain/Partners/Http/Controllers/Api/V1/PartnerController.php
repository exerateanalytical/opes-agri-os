<?php

namespace App\Domain\Partners\Http\Controllers\Api\V1;

use App\Domain\Partners\Http\Requests\StorePartnerInteractionRequest;
use App\Domain\Partners\Http\Requests\StorePartnerRequest;
use App\Domain\Partners\Http\Requests\UpdatePartnerRequest;
use App\Domain\Partners\Http\Resources\PartnerInteractionResource;
use App\Domain\Partners\Http\Resources\PartnerResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartnerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Partner::class);

        $partners = Partner::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return PartnerResource::collection($partners);
    }

    public function show(Partner $partner): PartnerResource
    {
        $this->authorize('view', $partner);

        return PartnerResource::make($partner->load('interactions'));
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $partner = Partner::create($request->validated() + [
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return PartnerResource::make($partner)->response()->setStatusCode(201);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): PartnerResource
    {
        $partner->update($request->validated());

        return PartnerResource::make($partner);
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $this->authorize('delete', $partner);

        $partner->delete();

        return response()->json(status: 204);
    }

    public function interactions(Partner $partner): AnonymousResourceCollection
    {
        $this->authorize('view', $partner);

        return PartnerInteractionResource::collection(
            $partner->interactions()->orderByDesc('interaction_date')->get()
        );
    }

    public function recordInteraction(StorePartnerInteractionRequest $request, Partner $partner): JsonResponse
    {
        $interaction = $partner->interactions()->create($request->validated() + [
            'recorded_by' => $request->user()->id,
        ]);

        return PartnerInteractionResource::make($interaction)->response()->setStatusCode(201);
    }
}
