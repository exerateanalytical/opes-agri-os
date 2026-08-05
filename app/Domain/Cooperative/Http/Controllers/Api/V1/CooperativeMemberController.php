<?php

namespace App\Domain\Cooperative\Http\Controllers\Api\V1;

use App\Domain\Cooperative\Http\Requests\RecordContributionRequest;
use App\Domain\Cooperative\Http\Requests\StoreCooperativeMemberRequest;
use App\Domain\Cooperative\Http\Requests\UpdateCooperativeMemberRequest;
use App\Domain\Cooperative\Http\Resources\CooperativeMemberResource;
use App\Domain\Cooperative\Http\Resources\MemberContributionResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\CooperativeMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CooperativeMemberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CooperativeMember::class);

        $members = CooperativeMember::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return CooperativeMemberResource::collection($members);
    }

    public function show(CooperativeMember $cooperativeMember): CooperativeMemberResource
    {
        $this->authorize('view', $cooperativeMember);

        return CooperativeMemberResource::make($cooperativeMember->load('contributions'));
    }

    public function store(StoreCooperativeMemberRequest $request): JsonResponse
    {
        $member = CooperativeMember::create($request->validated() + [
            'status' => 'active',
            'balance' => 0,
            'created_by' => $request->user()->id,
        ]);

        return CooperativeMemberResource::make($member)->response()->setStatusCode(201);
    }

    public function update(UpdateCooperativeMemberRequest $request, CooperativeMember $cooperativeMember): CooperativeMemberResource
    {
        $cooperativeMember->update($request->validated());

        return CooperativeMemberResource::make($cooperativeMember);
    }

    public function destroy(CooperativeMember $cooperativeMember): JsonResponse
    {
        $this->authorize('delete', $cooperativeMember);

        $cooperativeMember->delete();

        return response()->json(status: 204);
    }

    public function recordContribution(RecordContributionRequest $request, CooperativeMember $cooperativeMember): JsonResponse
    {
        $contribution = $cooperativeMember->contributions()->create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);

        $cooperativeMember->recomputeBalance();

        return MemberContributionResource::make($contribution)->response()->setStatusCode(201);
    }
}
