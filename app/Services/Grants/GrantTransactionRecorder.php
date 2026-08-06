<?php

namespace App\Services\Grants;

use App\Enums\PaymentMethod;
use App\Models\GrantProject;
use App\Models\User;
use App\Services\Accounting\RecordsBusinessEvents;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records money moving on a grant project — a receipt from the partner or
 * an expenditure against it — updates the project's cached running totals,
 * and posts the movement to the accounting ledger. Both directions are real
 * cash, so both post from the moment they're recorded, the same reasoning
 * LoanDisburser/LoanRepaymentRecorder apply to a loan (V3 M2). See
 * RecordsBusinessEvents::recordGrantReceipt()/recordGrantExpenditure().
 */
class GrantTransactionRecorder
{
    public function __construct(protected RecordsBusinessEvents $books) {}

    public function record(
        GrantProject $project,
        User $actor,
        string $type,
        float $amount,
        PaymentMethod $method,
        ?string $transactionDate = null,
        array $options = [],
    ): GrantProject {
        if (! in_array($type, ['receipt', 'expenditure'], true)) {
            throw new RuntimeException('Unknown grant transaction type.');
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        $currency = $options['currency'] ?? $project->currency;

        if ($currency !== $project->currency) {
            throw new RuntimeException('This transaction currency does not match the grant project\'s currency.');
        }

        return DB::transaction(function () use ($project, $actor, $type, $amount, $currency, $method, $transactionDate, $options) {
            $company = app(CurrentCompany::class)->get();

            // Re-read under a row lock: two concurrent expenditures against the
            // same grant must not both pass the balance check on stale data.
            $locked = GrantProject::query()->lockForUpdate()->findOrFail($project->getKey());

            if ($type === 'expenditure' && $amount > $locked->balance() + 0.01) {
                throw new RuntimeException('This expenditure is larger than what remains of the grant.');
            }

            $transaction = $locked->transactions()->create([
                'type' => $type,
                'amount' => $amount,
                'currency' => $currency,
                'transaction_date' => $transactionDate ?? now()->toDateString(),
                'method' => $method->value,
                'description' => $options['description'] ?? null,
                'recorded_by' => $actor->id,
            ]);

            $till = in_array($method, [PaymentMethod::Cash, PaymentMethod::MobileMoney], true) ? 'cash' : 'bank';

            // Grants are a real-money module: unlike a soft-fail such as
            // harvest valuation, a ledger posting failure here must roll
            // back the whole transaction rather than leave the cached
            // received/spent totals out of sync with the books. Deliberately
            // not using recordQuietly() — a Throwable from posting is
            // allowed to propagate and abort this DB::transaction().
            if ($type === 'receipt') {
                $locked->increment('received_amount', $amount);

                $this->books->recordGrantReceipt($transaction, $company, $till, $actor);
            } else {
                $locked->increment('spent_amount', $amount);

                $this->books->recordGrantExpenditure($transaction, $company, $till, $actor);
            }

            return $locked->refresh();
        });
    }
}
