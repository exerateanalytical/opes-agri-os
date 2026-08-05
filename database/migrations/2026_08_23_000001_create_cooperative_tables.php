<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cooperative & Farmer Groups (V3 M1) — membership registry and the
     * share-capital/savings contributions members make against it. Reuses
     * `Contact` (type=member) for the person record rather than a parallel
     * name/phone/address table, the same reasoning Procurement used for
     * suppliers. Loans, voting and governance are separate, later
     * milestones — a different domain shape again (loan ledgers, quorum
     * rules) that shouldn't be guessed at ahead of being built.
     */
    public function up(): void
    {
        Schema::create('cooperative_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('contact_id')->constrained()->cascadeOnDelete();
            $table->string('membership_number')->nullable();
            $table->date('joined_on')->nullable();
            $table->string('status')->default('active'); // active|suspended|exited
            // Cached rollup of contributions; recomputed on write, the same
            // pattern Contact::recomputeBalance() uses for document balances.
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'contact_id']);
            $table->unique(['company_id', 'membership_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('member_contributions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('cooperative_member_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // share_capital|savings
            $table->decimal('amount', 15, 2);
            $table->date('contributed_on');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'cooperative_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_contributions');
        Schema::dropIfExists('cooperative_members');
    }
};
