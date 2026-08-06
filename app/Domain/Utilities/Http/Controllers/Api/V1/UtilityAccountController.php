<?php

namespace App\Domain\Utilities\Http\Controllers\Api\V1;

use App\Domain\Utilities\Http\Requests\StoreUtilityAccountRequest;
use App\Domain\Utilities\Http\Requests\StoreUtilityReadingRequest;
use App\Domain\Utilities\Http\Requests\UpdateUtilityAccountRequest;
use App\Domain\Utilities\Http\Resources\UtilityAccountResource;
use App\Domain\Utilities\Http\Resources\UtilityReadingResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\UtilityAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UtilityAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UtilityAccount::class);

        $accounts = UtilityAccount::query()
            ->when($request->filled('utility_type'), fn ($q) => $q->where('utility_type', $request->string('utility_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return UtilityAccountResource::collection($accounts);
    }

    public function show(UtilityAccount $utilityAccount): UtilityAccountResource
    {
        $this->authorize('view', $utilityAccount);

        return UtilityAccountResource::make($utilityAccount->load('readings'));
    }

    public function store(StoreUtilityAccountRequest $request): JsonResponse
    {
        $account = UtilityAccount::create($request->validated() + [
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return UtilityAccountResource::make($account)->response()->setStatusCode(201);
    }

    public function update(UpdateUtilityAccountRequest $request, UtilityAccount $utilityAccount): UtilityAccountResource
    {
        $utilityAccount->update($request->validated());

        return UtilityAccountResource::make($utilityAccount);
    }

    public function destroy(UtilityAccount $utilityAccount): JsonResponse
    {
        $this->authorize('delete', $utilityAccount);

        $utilityAccount->delete();

        return response()->json(status: 204);
    }

    public function readings(Request $request, UtilityAccount $utilityAccount): AnonymousResourceCollection
    {
        $this->authorize('view', $utilityAccount);

        return UtilityReadingResource::collection(
            $utilityAccount->readings()
                ->orderByDesc('read_on')
                ->orderByDesc('id')
                ->cursorPaginate((int) $request->integer('per_page', 25))
        );
    }

    public function recordReading(StoreUtilityReadingRequest $request, UtilityAccount $utilityAccount): JsonResponse
    {
        $reading = $utilityAccount->readings()->create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);

        return UtilityReadingResource::make($reading)->response()->setStatusCode(201);
    }
}
