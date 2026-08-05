<?php

namespace App\Domain\Partners\Http\Controllers\Api\V1;

use App\Domain\Partners\Http\Requests\RecordGrantTransactionRequest;
use App\Domain\Partners\Http\Requests\StoreGrantProjectRequest;
use App\Domain\Partners\Http\Requests\UpdateGrantProjectRequest;
use App\Domain\Partners\Http\Resources\GrantProjectResource;
use App\Domain\Partners\Http\Resources\GrantTransactionResource;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\GrantProject;
use App\Services\Grants\GrantTransactionRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GrantProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GrantProject::class);

        $projects = GrantProject::query()
            ->when($request->filled('partner_id'), fn ($q) => $q->where('partner_id', $request->string('partner_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return GrantProjectResource::collection($projects);
    }

    public function show(GrantProject $grantProject): GrantProjectResource
    {
        $this->authorize('view', $grantProject);

        return GrantProjectResource::make($grantProject->load('transactions'));
    }

    public function store(StoreGrantProjectRequest $request): JsonResponse
    {
        $project = GrantProject::create($request->validated() + [
            'currency' => $request->validated('currency') ?? 'USD',
            'status' => 'planned',
            'created_by' => $request->user()->id,
        ]);

        return GrantProjectResource::make($project)->response()->setStatusCode(201);
    }

    public function update(UpdateGrantProjectRequest $request, GrantProject $grantProject): GrantProjectResource
    {
        $grantProject->update($request->validated());

        return GrantProjectResource::make($grantProject);
    }

    public function destroy(GrantProject $grantProject): JsonResponse
    {
        $this->authorize('delete', $grantProject);

        $grantProject->delete();

        return response()->json(status: 204);
    }

    public function transactions(GrantProject $grantProject): AnonymousResourceCollection
    {
        $this->authorize('view', $grantProject);

        return GrantTransactionResource::collection(
            $grantProject->transactions()->orderByDesc('transaction_date')->get()
        );
    }

    public function recordTransaction(RecordGrantTransactionRequest $request, GrantProject $grantProject, GrantTransactionRecorder $recorder): GrantProjectResource
    {
        $updated = $recorder->record(
            $grantProject,
            $request->user(),
            $request->validated('type'),
            (float) $request->validated('amount'),
            PaymentMethod::from($request->validated('method')),
            $request->validated('transaction_date'),
            ['description' => $request->validated('description')],
        );

        return GrantProjectResource::make($updated->load('transactions'));
    }
}
