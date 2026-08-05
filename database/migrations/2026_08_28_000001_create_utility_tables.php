<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Utility Management (V4 Phase 2b) — electricity, water, internet and
     * other metered accounts a farm holds, and the periodic readings taken
     * against them. Consumption tracking is the genuinely new thing here:
     * the Expenses screen already records what a utility bill cost (its
     * `electricity`/`water` categories), this records what was actually
     * used. See docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::create('utility_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->string('utility_type'); // electricity|water|internet|gas|other
            $table->string('provider_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('status')->default('active'); // active|inactive
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'farm_id']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('utility_readings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('utility_account_id')->constrained()->cascadeOnDelete();
            $table->date('read_on');
            $table->decimal('meter_reading', 15, 2)->nullable();
            // What was used since the previous reading — entered directly
            // rather than derived, since a meter can be replaced or reset
            // between readings and the running total would then lie.
            $table->decimal('consumption', 15, 2)->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'utility_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_readings');
        Schema::dropIfExists('utility_accounts');
    }
};
