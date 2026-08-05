<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Machinery & Equipment (V4 Phase 2a) — a maintenance log against a
     * FixedAsset, keyed the same way AnimalHealthRecord is keyed to Animal:
     * a dated record with a type, a cost, and an optional next-due date.
     * A tractor is a fixed asset with a maintenance log, not a new concept
     * — see docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::create('asset_maintenance_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->string('maintenance_type'); // service|repair|inspection
            $table->string('description');
            $table->date('performed_on');
            $table->decimal('cost', 15, 2)->nullable();
            $table->date('next_due_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'fixed_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_records');
    }
};
