<?php

namespace App\Domain\Assets\Http\Controllers\Api\V1;

use App\Domain\Assets\Http\Requests\StoreFleetTripRequest;
use App\Domain\Assets\Http\Resources\FleetTripResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FleetTripController extends Controller
{
    public function index(FixedAsset $fixedAsset): AnonymousResourceCollection
    {
        Gate::authorize('assets.view');

        return FleetTripResource::collection(
            $fixedAsset->fleetTrips()->orderByDesc('started_on')->get()
        );
    }

    public function store(StoreFleetTripRequest $request, FixedAsset $fixedAsset): JsonResponse
    {
        $trip = $fixedAsset->fleetTrips()->create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);

        return FleetTripResource::make($trip)->response()->setStatusCode(201);
    }
}
