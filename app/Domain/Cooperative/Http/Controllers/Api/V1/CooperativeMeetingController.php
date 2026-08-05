<?php

namespace App\Domain\Cooperative\Http\Controllers\Api\V1;

use App\Domain\Cooperative\Http\Requests\RecordAttendanceRequest;
use App\Domain\Cooperative\Http\Requests\StoreMeetingRequest;
use App\Domain\Cooperative\Http\Requests\UpdateMeetingRequest;
use App\Domain\Cooperative\Http\Resources\CooperativeMeetingResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\CooperativeMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CooperativeMeetingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CooperativeMeeting::class);

        $meetings = CooperativeMeeting::query()
            ->withCount('attendances')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return CooperativeMeetingResource::collection($meetings);
    }

    public function show(CooperativeMeeting $cooperativeMeeting): CooperativeMeetingResource
    {
        $this->authorize('view', $cooperativeMeeting);

        return CooperativeMeetingResource::make($cooperativeMeeting->loadCount('attendances'));
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $meeting = CooperativeMeeting::create($request->validated() + [
            'quorum_required' => $request->validated('quorum_required') ?? 0,
            'status' => 'scheduled',
            'created_by' => $request->user()->id,
        ]);

        return CooperativeMeetingResource::make($meeting)->response()->setStatusCode(201);
    }

    public function update(UpdateMeetingRequest $request, CooperativeMeeting $cooperativeMeeting): CooperativeMeetingResource
    {
        $cooperativeMeeting->update($request->validated());

        return CooperativeMeetingResource::make($cooperativeMeeting->loadCount('attendances'));
    }

    public function recordAttendance(RecordAttendanceRequest $request, CooperativeMeeting $cooperativeMeeting): CooperativeMeetingResource
    {
        $cooperativeMeeting->attendances()->firstOrCreate([
            'cooperative_member_id' => $request->validated('cooperative_member_id'),
        ]);

        return CooperativeMeetingResource::make($cooperativeMeeting->loadCount('attendances'));
    }
}
