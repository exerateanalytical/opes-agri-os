<?php

namespace App\Domain\Sales\Http\Controllers\Api\V1;

use App\Domain\Sales\Http\Requests\RecordPaymentRequest;
use App\Domain\Sales\Http\Resources\PaymentResource;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Document;
use App\Models\Payment;
use App\Services\PaymentRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::query()
            ->when($request->filled('contact_id'), fn ($q) => $q->where('contact_id', $request->string('contact_id')))
            ->with(['allocations', 'receipt'])
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return PaymentResource::collection($payments);
    }

    public function show(Payment $payment): PaymentResource
    {
        $this->authorize('view', $payment);

        return PaymentResource::make($payment->load(['allocations', 'receipt']));
    }

    public function store(
        RecordPaymentRequest $request,
        Document $document,
        PaymentRecorder $recorder,
    ): JsonResponse {
        $payment = $recorder->record(
            document: $document,
            cashier: $request->user(),
            amount: (float) $request->validated('amount'),
            method: PaymentMethod::from($request->validated('method')),
            reference: $request->validated('reference'),
            receiptFormat: $request->validated('receipt_format', 'thermal80'),
            receivedAt: $request->validated('received_at') ? Carbon::parse($request->validated('received_at')) : null,
        );

        return PaymentResource::make($payment->load(['allocations', 'receipt']))->response()->setStatusCode(201);
    }
}
