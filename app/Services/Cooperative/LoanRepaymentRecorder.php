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
 * Records a repayment against an active loan, splits it between principal
 * and interest, and posts that split to the books.
 *
 * The split is proportional to the loan's own flat terms — interest is
 * `total_interest / total_repayable` of every franc paid — capped at what's
 * left to recognise so the running total can never post more interest than
 * the loan actually carries, the same "last one absorbs the difference"
 * discipline `RecordsBusinessEvents::revenueByAccount()` uses for a
 * document's line split, just spread across repayments instead of lines.
 */
class LoanRepaymentRecorder
{
    public function __construct(protected RecordsBusinessEvents $books) {}

    public function record(Loan $loan, User $actor, float $amount, PaymentMethod $method, ?string $paidOn = null, array $options = []): Loan
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Repayment amount must be greater than zero.');
        }

        $reference = $options['reference'] ?? null;
        $reference = ($reference === '') ? null : $reference;

        return DB::transaction(function () use ($loan, $actor, $amount, $method, $paidOn, $options, $reference) {
            // Re-read under a row lock: two requests recording a repayment
            // against the same loan at once must not both pass the balance
            // check on stale data — see PaymentRecorder::record() for the
            // same guard against the same race.
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            // A retried/duplicate request carrying the same client
            // reference is recognised as the same repayment, not a new
            // one — replaying it must be harmless rather than either
            // double-posting the money or erroring the retry away.
            if ($reference !== null) {
                $existing = $loan->repayments()->where('reference', $reference)->first();

                if ($existing !== null) {
                    return $loan;
                }
            }

            if ($loan->status !== 'active') {
                throw new RuntimeException('Only an active loan can receive a repayment.');
            }

            if ($amount > round((float) $loan->balance, 2) + 0.01) {
                throw new RuntimeException('This repayment is larger than what remains on the loan.');
            }

            $company = app(CurrentCompany::class)->get();

            $totalRepayable = (float) $loan->total_repayable;
            $interestRatio = $totalRepayable > 0 ? (float) $loan->total_interest / $totalRepayable : 0.0;
            $remainingInterest = round((float) $loan->total_interest - (float) $loan->interest_recognized, 2);

            $interestPortion = min($remainingInterest, round($amount * $interestRatio, 2));
            $interestPortion = max($interestPortion, 0.0);
            $principalPortion = round($amount - $interestPortion, 2);

            $repayment = $loan->repayments()->create([
                'amount' => $amount,
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'method' => $method,
                'paid_on' => $paidOn ?? now()->toDateString(),
                'reference' => $reference,
                'notes' => $options['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $newBalance = round((float) $loan->balance - $amount, 2);

            $loan->forceFill([
                'balance' => max($newBalance, 0),
                'interest_recognized' => round((float) $loan->interest_recognized + $interestPortion, 2),
                'status' => $newBalance <= 0.01 ? 'closed' : 'active',
            ])->save();

            $destination = in_array($method, [PaymentMethod::Cash, PaymentMethod::MobileMoney], true) ? 'cash' : 'bank';

            $this->books->recordQuietly(
                fn () => $this->books->recordLoanRepayment($repayment, $company, $destination, $actor)
            );

            return $loan;
        });
    }
}
