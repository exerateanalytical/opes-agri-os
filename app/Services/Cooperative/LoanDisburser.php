<?php

namespace App\Services\Cooperative;

use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\User;
use App\Services\Accounting\RecordsBusinessEvents;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves a loan from pending to active: sets its totals, opens its balance,
 * and posts the disbursement to the books — a loan is real cash leaving,
 * unlike a harvest or a member's own contribution, so it is not deferred
 * the way those are. See RecordsBusinessEvents::recordLoanDisbursement().
 */
class LoanDisburser
{
    public function __construct(protected RecordsBusinessEvents $books) {}

    public function disburse(Loan $loan, User $actor, PaymentMethod $method, ?string $disbursedOn = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $method, $disbursedOn) {
            // Re-read under a row lock: two requests disbursing the same
            // loan at once must not both pass the pending check on stale
            // data — see PaymentRecorder::record() for the same guard
            // against the same race.
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            if ($loan->status !== 'pending') {
                throw new RuntimeException('Only a pending loan can be disbursed.');
            }

            $company = app(CurrentCompany::class)->get();

            $principal = round((float) $loan->principal, 2);
            $totalInterest = round($principal * (float) $loan->interest_rate, 2);

            $loan->forceFill([
                'total_interest' => $totalInterest,
                'total_repayable' => round($principal + $totalInterest, 2),
                'balance' => round($principal + $totalInterest, 2),
                'status' => 'active',
                'disbursed_on' => $disbursedOn ?? now()->toDateString(),
            ])->save();

            $destination = in_array($method, [PaymentMethod::Cash, PaymentMethod::MobileMoney], true) ? 'cash' : 'bank';

            $this->books->recordQuietly(
                fn () => $this->books->recordLoanDisbursement($loan, $company, $destination, $actor)
            );

            return $loan;
        });
    }
}
