<?php

namespace App\Domain\Assets\Http\Controllers\Api\V1;

use App\Domain\Assets\Http\Requests\StoreAssetMaintenanceRecordRequest;
use App\Domain\Assets\Http\Resources\AssetMaintenanceRecordResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssetMaintenanceRecordController extends Controller
{
    public function index(FixedAsset $fixedAsset): AnonymousResourceCollection
    {
        Gate::authorize('assets.view');

        return AssetMaintenanceRecordResource::collection(
            $fixedAsset->maintenanceRecords()->orderByDesc('performed_on')->get()
        );
    }

    public function store(StoreAssetMaintenanceRecordRequest $request, FixedAsset $fixedAsset): JsonResponse
    {
        $record = $fixedAsset->maintenanceRecords()->create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);

        return AssetMaintenanceRecordResource::make($record)->response()->setStatusCode(201);
    }
}
