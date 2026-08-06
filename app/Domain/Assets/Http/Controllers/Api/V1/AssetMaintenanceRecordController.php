<?php

namespace App\Domain\Assets\Http\Controllers\Api\V1;

use App\Domain\Assets\Http\Requests\StoreAssetMaintenanceRecordRequest;
use App\Domain\Assets\Http\Resources\AssetMaintenanceRecordResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssetMaintenanceRecordController extends Controller
{
    public function index(Request $request, FixedAsset $fixedAsset): AnonymousResourceCollection
    {
        Gate::authorize('assets.view');

        return AssetMaintenanceRecordResource::collection(
            $fixedAsset->maintenanceRecords()
                ->orderByDesc('performed_on')
                ->orderByDesc('id')
                ->cursorPaginate((int) $request->integer('per_page', 25))
        );
    }

    public function store(StoreAssetMaintenanceRecordRequest $request, FixedAsset $fixedAsset): JsonResponse
    {
        if ($fixedAsset->isDisposed()) {
            abort(422, 'Cannot record maintenance against a disposed asset.');
        }

        $record = $fixedAsset->maintenanceRecords()->create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);

        return AssetMaintenanceRecordResource::make($record)->response()->setStatusCode(201);
    }
}
