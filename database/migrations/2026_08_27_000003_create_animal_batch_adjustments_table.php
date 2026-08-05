<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Livestock V2 M2 follow-up: a per-adjustment audit trail for a batch's
     * running count, and the batch's own per-head cost so a mortality/loss
     * adjustment can be valued onto the books. Promoting the running total
     * to a ledger-style table, exactly as flagged in
     * docs/architecture/agri-platform-roadmap.md §6 — the running
     * `current_count` on `animal_batches` stays as the fast-read figure,
     * this table is what lets a business reconstruct *when* and *why* it
     * moved.
     */
    public function up(): void
    {
        Schema::table('animal_batches', function (Blueprint $table) {
            // What one head in this batch is carried at, so a mortality/loss
            // adjustment has a value to write off. Nullable: a business that
            // never prices its flock keeps recording counts exactly as
            // before, unposted.
            $table->decimal('unit_cost', 12, 2)->nullable()->after('current_count');
        });

        Schema::create('animal_batch_adjustments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('animal_batch_id')->constrained()->cascadeOnDelete();
            // Positive for hatching/additions/purchases, negative for
            // mortality, culling or a non-sale loss.
            $table->integer('change');
            // The count immediately after this adjustment, so a row can be
            // read on its own without replaying the whole history.
            $table->unsignedInteger('resulting_count');
            $table->string('reason')->nullable(); // e.g. mortality, culled, hatched, sold
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'animal_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_batch_adjustments');

        Schema::table('animal_batches', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
