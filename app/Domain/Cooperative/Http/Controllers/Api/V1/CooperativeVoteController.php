<?php

namespace App\Domain\Cooperative\Http\Controllers\Api\V1;

use App\Domain\Cooperative\Http\Requests\CastVoteRequest;
use App\Domain\Cooperative\Http\Requests\StoreVoteRequest;
use App\Domain\Cooperative\Http\Resources\CooperativeVoteResource;
use App\Http\Controllers\Api\V1\Controller;
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
        $vote = CooperativeVote::create($request->validated() + [
            'opened_on' => $request->validated('opened_on') ?? now()->toDateString(),
            'status' => 'open',
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
