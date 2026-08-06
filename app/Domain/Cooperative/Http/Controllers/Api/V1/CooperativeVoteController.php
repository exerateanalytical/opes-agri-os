<?php

namespace App\Domain\Cooperative\Http\Controllers\Api\V1;

use App\Domain\Cooperative\Http\Requests\CastVoteRequest;
use App\Domain\Cooperative\Http\Requests\StoreVoteRequest;
use App\Domain\Cooperative\Http\Resources\CooperativeVoteResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\CooperativeMeeting;
use App\Models\CooperativeMember;
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
         * "You must convene before you can decide": a vote tied to a
         * meeting cannot open until that meeting has actually been held —
         * gating quorum only on `status === 'held'` while leaving a
         * `scheduled` meeting free to open votes made the gate trivial to
         * bypass by simply never flipping status (attendance is recorded
         * independently of it). Requiring `held` closes that hole outright:
         * a meeting that hasn't convened yet cannot be a body a resolution
         * is raised in front of, whether or not it happens to have logged
         * enough attendance. Once held, quorum is re-checked every time —
         * a meeting that convened without enough attendees cannot decide
         * anything either. A vote raised with no meeting at all is
         * unaffected, exactly as before.
         */
        if ($meetingId !== null) {
            $meeting = CooperativeMeeting::findOrFail($meetingId);

            if ($meeting->status !== 'held') {
                throw new RuntimeException('A vote cannot be opened against a meeting that has not been held yet.');
            }

            if (! $meeting->quorumMet()) {
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

        $memberId = $request->validated('cooperative_member_id');

        // A vote tied to a meeting that tracks quorum only means something
        // cast by someone who was actually there — mirrors the quorum gate
        // at open time, just per-ballot instead of per-meeting.
        if ($cooperativeVote->cooperative_meeting_id !== null) {
            $meeting = $cooperativeVote->meeting;

            if ($meeting !== null && $meeting->quorum_required > 0) {
                $attended = $meeting->attendances()
                    ->where('cooperative_member_id', $memberId)
                    ->exists();

                if (! $attended) {
                    throw new RuntimeException('Only a member who attended this meeting can vote on a resolution raised there.');
                }
            }
        }

        $member = CooperativeMember::query()->findOrFail($memberId);

        $cooperativeVote->ballots()->firstOrCreate(
            ['cooperative_member_id' => $memberId],
            ['choice' => $request->validated('choice'), 'weight_at_cast' => $member->vote_weight],
        );

        return CooperativeVoteResource::make($cooperativeVote);
    }
}
