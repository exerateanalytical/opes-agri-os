<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A planting: one field, one season, one crop. The crop itself is an
     * `Item` (type=product, track_stock=true) rather than a separate lookup
     * table — its eventual harvest is the same tracked thing stock already
     * models. `status` and `growth_stage` are plain strings, not enum
     * columns, so either list can change without a migration. See
     * docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::create('crop_cycles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('field_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('season_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('planned'); // planned|planted|growing|harvested|closed
            $table->string('growth_stage')->nullable();
            $table->date('planned_planting_date')->nullable();
            $table->date('actual_planting_date')->nullable();
            $table->date('planned_harvest_date')->nullable();
            $table->date('actual_harvest_date')->nullable();
            $table->decimal('planned_yield_qty', 15, 3)->nullable();
            $table->decimal('actual_yield_qty', 15, 3)->nullable();
            $table->string('yield_unit')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'field_id', 'season_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_cycles');
    }
};
