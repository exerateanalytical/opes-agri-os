<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `reference` already existed as a free-text field on a repayment; this
     * makes it double as an idempotency key when a caller supplies one — a
     * retried repayment request replayed with the same (loan_id, reference)
     * is recognised as the same repayment rather than posting a second one.
     * Unique per loan rather than globally: two different loans are free to
     * reuse the same external reference, and MySQL's unique index treats
     * every NULL as distinct, so callers who don't supply one are
     * unaffected, exactly as before.
     */
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->unique(['loan_id', 'reference'], 'loan_repayments_loan_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropUnique('loan_repayments_loan_reference_unique');
        });
    }
};
