<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Microfinance & Credit (V3 M2) — loans against a cooperative member,
     * flat interest (principal × rate, no amortization schedule), with
     * disbursement and repayments posting to the accounting ledger. Unlike
     * a harvest or a member's contribution, a loan is real cash the
     * business does not get back automatically — it is owed — so, unlike
     * those, it is posted from day one rather than deferred. See
     * docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('cooperative_member_id')->constrained()->cascadeOnDelete();
            $table->decimal('principal', 15, 2);
            // Flat rate, e.g. 0.10 for 10% of principal, applied once —
            // not compounded, not amortized. total_interest is stored
            // rather than recomputed so a later rate change on the
            // company/product side can't rewrite an existing loan's terms.
            $table->decimal('interest_rate', 6, 4)->default(0);
            $table->decimal('total_interest', 15, 2)->default(0);
            $table->decimal('total_repayable', 15, 2)->default(0);
            // What's still owed — starts at total_repayable on disbursement,
            // falls by each repayment's full amount.
            $table->decimal('balance', 15, 2)->default(0);
            // Cumulative interest already recognised into 771, so each
            // repayment's split can cap itself at what's left to recognise
            // rather than drifting past total_interest.
            $table->decimal('interest_recognized', 15, 2)->default(0);
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->string('status')->default('pending'); // pending|active|closed|defaulted
            $table->date('disbursed_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'cooperative_member_id']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_portion', 15, 2);
            $table->decimal('interest_portion', 15, 2);
            $table->string('method')->default('cash'); // cash|bank_transfer|mobile_money|card
            $table->date('paid_on');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'loan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loans');
    }
};
