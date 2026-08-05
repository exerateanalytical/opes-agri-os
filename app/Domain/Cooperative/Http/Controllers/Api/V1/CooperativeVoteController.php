<?php

namespace App\Domain\Cooperative\Http\Controllers\Api\V1;

use App\Domain\Cooperative\Http\Requests\CastVoteRequest;
use App\Domain\Cooperative\Http\Requests\StoreVoteRequest;
use App\Domain\Cooperative\Http\Resources\CooperativeVoteResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\CooperativeMeeting;
use App\Models\CooperativeVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

class CooperativeVoteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CooperativeVote::class);

        $votes = CooperativeVote::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('cooperative_meeting_id'), fn ($q) => $q->where('cooperative_meeting_id', $request->string('cooperative_meeting_id')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return CooperativeVoteResource::collection($votes);
    }

    public function show(CooperativeVote $cooperativeVote): CooperativeVoteResource
    {
        $this->authorize('view', $cooperativeVote);

        return CooperativeVoteResource::make($cooperativeVote);
    }

    public function store(StoreVoteRequest $request): JsonResponse
    {
        $meetingId = $request->validated('cooperative_meeting_id');

        /*
         * Quorum as a hard gate rather than the informational flag V3 M3
         * shipped with: a vote tied to a meeting that has already been held
         * without hitting its own `quorum_required` cannot open, because a
         * meeting that never had enough attendees to decide anything is not
         * a body a resolution can be raised in front of. A meeting that is
         * still only `scheduled` has not happened yet — attendance isn't in
         * yet either — so it is not held to quorum here; a vote raised with
         * no meeting at all is unaffected, exactly as before.
         */
        if ($meetingId !== null) {
            $meeting = CooperativeMeeting::findOrFail($meetingId);

            if ($meeting->status === 'held' && ! $meeting->quorumMet()) {
                throw new RuntimeException('This meeting did not reach quorum, so no vote can be opened against it.');
            }
        }

        $vote = CooperativeVote::create($request->validated() + [
            'opened_on' => $request->validated('opened_on') ?? now()->toDateString(),
            'status' => 'open',
            'weighted' => $request->boolean('weighted'),
            'created_by' => $request->user()->id,
        ]);

        return CooperativeVoteResource::make($vote)->response()->setStatusCode(201);
    }

    public function close(CooperativeVote $cooperativeVote): CooperativeVoteResource
    {
        $this->authorize('update', $cooperativeVote);

        $cooperativeVote->update(['status' => 'closed', 'closed_on' => now()->toDateString()]);

        return CooperativeVoteResource::make($cooperativeVote);
    }

    public function castVote(CastVoteRequest $request, CooperativeVote $cooperativeVote): CooperativeVoteResource
    {
        if ($cooperativeVote->status !== 'open') {
            throw new RuntimeException('This vote is closed.');
        }

        $cooperativeVote->ballots()->firstOrCreate(
            ['cooperative_member_id' => $request->validated('cooperative_member_id')],
            ['choice' => $request->validated('choice')],
        );

        return CooperativeVoteResource::make($cooperativeVote);
    }
}
