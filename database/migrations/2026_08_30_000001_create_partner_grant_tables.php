<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partner & NGO CRM and Project & Grant Management (V4 Phase 4) — a
     * dedicated `Partner` (an NGO, donor, government or cooperative partner
     * the business deals with) rather than a `Contact`: a partner is not a
     * customer or a supplier, it funds or co-runs work, which is a different
     * relationship than anything Sales models. `GrantProject` is the funded
     * work itself, optionally tied to a `Partner`, with `GrantTransaction`
     * recording both money coming in (a disbursement/receipt from the
     * partner) and money going out (project expenditure) — both are real
     * cash, so both post to the accounting ledger. See
     * docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // ngo|donor|government|cooperative_partner|other
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active'); // active|inactive
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('partner_interactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('partner_id')->constrained()->cascadeOnDelete();
            $table->date('interaction_date');
            $table->string('type'); // meeting|call|email|visit|other
            $table->text('summary');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'partner_id']);
        });

        Schema::create('grant_projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('planned'); // planned|active|completed|cancelled
            // Cached running totals, the same reasoning Loan caches its
            // balance rather than summing repayments on every read.
            $table->decimal('received_amount', 15, 2)->default(0);
            $table->decimal('spent_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'partner_id']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('grant_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('grant_project_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // receipt|expenditure
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD'); // must match the parent GrantProject's currency
            $table->date('transaction_date');
            $table->string('method')->nullable(); // cash|bank|mobile_money — which till it moved through
            $table->text('description')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'grant_project_id']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grant_transactions');
        Schema::dropIfExists('grant_projects');
        Schema::dropIfExists('partner_interactions');
        Schema::dropIfExists('partners');
    }
};
