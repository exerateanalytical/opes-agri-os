<?php

namespace App\Domain\Sales\Http\Controllers\Api\V1;

use App\Domain\Sales\Http\Requests\StoreItemRequest;
use App\Domain\Sales\Http\Requests\UpdateItemRequest;
use App\Domain\Sales\Http\Resources\ItemResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function store(StoreItemRequest $request): \Illuminate\Http\JsonResponse
    {
        $item = Item::create($request->validated());

        return ItemResource::make($item)->response()->setStatusCode(201);
    }

    public function update(UpdateItemRequest $request, Item $item): ItemResource
    {
        $item->update($request->validated());

        return ItemResource::make($item);
    }

    public function destroy(Item $item): \Illuminate\Http\Response
    {
        $this->authorize('delete', $item);

        $item->delete();

        return response()->noContent();
    }
}
