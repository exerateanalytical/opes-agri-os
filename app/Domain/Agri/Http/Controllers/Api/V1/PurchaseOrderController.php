<?php

namespace App\Domain\Agri\Http\Controllers\Api\V1;

use App\Domain\Agri\Http\Requests\ReceivePurchaseOrderRequest;
use App\Domain\Agri\Http\Requests\StorePurchaseOrderRequest;
use App\Domain\Agri\Http\Requests\UpdatePurchaseOrderRequest;
use App\Domain\Agri\Http\Resources\PurchaseOrderResource;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\PurchaseOrder;
use App\Services\Agri\PurchaseOrderIssuer;
use App\Services\Agri\PurchaseOrderReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::query()
            ->with('lines')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->string('supplier_id')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return PurchaseOrderResource::collection($orders);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('view', $purchaseOrder);

        return PurchaseOrderResource::make($purchaseOrder->load('lines'));
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $purchaseOrder = DB::transaction(function () use ($data, $request) {
            $po = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'number' => null,
                'status' => 'draft',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['lines'] as $line) {
                $po->lines()->create([
                    'item_id' => $line['item_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                ]);
            }

            $po->recalculateTotals();

            return $po;
        });

        return PurchaseOrderResource::make($purchaseOrder->load('lines'))->response()->setStatusCode(201);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $purchaseOrder) {
            $purchaseOrder->update([
                'supplier_id' => $data['supplier_id'] ?? $purchaseOrder->supplier_id,
                'order_date' => $data['order_date'] ?? $purchaseOrder->order_date,
                'expected_date' => $data['expected_date'] ?? $purchaseOrder->expected_date,
                'notes' => $data['notes'] ?? $purchaseOrder->notes,
            ]);

            if (isset($data['lines'])) {
                $purchaseOrder->lines()->delete();

                foreach ($data['lines'] as $line) {
                    $purchaseOrder->lines()->create([
                        'item_id' => $line['item_id'] ?? null,
                        'description' => $line['description'] ?? null,
                        'quantity' => $line['quantity'],
                        'unit_cost' => $line['unit_cost'],
                    ]);
                }
            }

            $purchaseOrder->recalculateTotals();
        });

        return PurchaseOrderResource::make($purchaseOrder->load('lines'));
    }

    public function issue(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderIssuer $issuer): PurchaseOrderResource
    {
        $this->authorize('issue', $purchaseOrder);

        $issued = $issuer->issue($purchaseOrder, $request->user());

        return PurchaseOrderResource::make($issued->load('lines'));
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrderReceiver $receiver): PurchaseOrderResource
    {
        $received = $receiver->receive(
            $purchaseOrder,
            $request->validated('lines'),
            [
                'received_on' => $request->validated('received_on'),
                'location_id' => $request->validated('location_id'),
                'record_expense' => $request->validated('record_expense'),
                'vat_rate' => $request->validated('vat_rate'),
                'payment_method' => $request->validated('payment_method'),
                'due_date' => $request->validated('due_date'),
            ],
            $request->user(),
        );

        return PurchaseOrderResource::make($received->load('lines'));
    }
}
