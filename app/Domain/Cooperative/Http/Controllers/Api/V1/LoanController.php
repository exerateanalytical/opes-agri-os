<?php

namespace App\Domain\Cooperative\Http\Controllers\Api\V1;

use App\Domain\Cooperative\Http\Requests\DisburseLoanRequest;
use App\Domain\Cooperative\Http\Requests\RecordRepaymentRequest;
use App\Domain\Cooperative\Http\Requests\StoreLoanRequest;
use App\Domain\Cooperative\Http\Resources\LoanResource;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Loan;
use App\Services\Cooperative\LoanDisburser;
use App\Services\Cooperative\LoanRepaymentRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Loan::class);

        $loans = Loan::query()
            ->when($request->filled('cooperative_member_id'), fn ($q) => $q->where('cooperative_member_id', $request->string('cooperative_member_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 25));

        return LoanResource::collection($loans);
    }

    public function show(Loan $loan): LoanResource
    {
        $this->authorize('view', $loan);

        return LoanResource::make($loan->load('repayments'));
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $loan = Loan::create($request->validated() + [
            'interest_rate' => $request->validated('interest_rate') ?? 0,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return LoanResource::make($loan)->response()->setStatusCode(201);
    }

    public function disburse(DisburseLoanRequest $request, Loan $loan, LoanDisburser $disburser): LoanResource
    {
        $disbursed = $disburser->disburse(
            $loan,
            $request->user(),
            PaymentMethod::from($request->validated('method')),
            $request->validated('disbursed_on'),
        );

        return LoanResource::make($disbursed);
    }

    public function recordRepayment(RecordRepaymentRequest $request, Loan $loan, LoanRepaymentRecorder $recorder): LoanResource
    {
        $updated = $recorder->record(
            $loan,
            $request->user(),
            (float) $request->validated('amount'),
            PaymentMethod::from($request->validated('method')),
            $request->validated('paid_on'),
            [
                'reference' => $request->validated('reference'),
                'notes' => $request->validated('notes'),
            ],
        );

        return LoanResource::make($updated->load('repayments'));
    }
}
