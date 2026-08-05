<?php

namespace App\Domain\Sales\Http\Controllers\Api\V1;

use App\Domain\Sales\Http\Requests\StoreItemRequest;
use App\Domain\Sales\Http\Requests\UpdateItemRequest;
use App\Domain\Sales\Http\Resources\ItemResource;
use App\Domain\Sales\Http\Resources\StockMovementResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Item::class);

        $items = Item::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return ItemResource::collection($items);
    }

    public function show(Item $item): ItemResource
    {
        $this->authorize('view', $item);

        return ItemResource::make($item);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = Item::create($request->validated());

        return ItemResource::make($item)->response()->setStatusCode(201);
    }

    public function update(UpdateItemRequest $request, Item $item): ItemResource
    {
        $item->update($request->validated());

        return ItemResource::make($item);
    }

    public function destroy(Item $item): Response
    {
        $this->authorize('delete', $item);

        $item->delete();

        return response()->noContent();
    }

    /**
     * Every movement recorded against a batch — a harvest or a delivery in,
     * a sale or a void out — in the order it happened. The chain-of-custody
     * a batch's `batch_number` already carried since it was first written
     * (see docs/architecture/agri-platform-roadmap.md); this just reads it
     * back rather than adding anywhere new for it to be recorded.
     */
    public function traceBatch(Item $item, string $batchNumber): AnonymousResourceCollection
    {
        $this->authorize('view', $item);

        $movements = StockMovement::query()
            ->where('item_id', $item->id)
            ->where('batch_number', $batchNumber)
            ->orderBy('occurred_at')
            ->get();

        return StockMovementResource::collection($movements);
    }
}
