<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field operations (V4 Phase 1): soil test records and irrigation logs,
     * both keyed to Field the same way AnimalHealthRecord is keyed to
     * Animal — a dated record with values and notes, nothing more. See
     * docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::create('soil_test_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('field_id')->constrained()->cascadeOnDelete();
            $table->date('tested_on');
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('nitrogen_ppm', 8, 2)->nullable();
            $table->decimal('phosphorus_ppm', 8, 2)->nullable();
            $table->decimal('potassium_ppm', 8, 2)->nullable();
            $table->decimal('organic_matter_pct', 5, 2)->nullable();
            $table->text('recommendations')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'field_id']);
        });

        Schema::create('irrigation_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('field_id')->constrained()->cascadeOnDelete();
            $table->date('irrigated_on');
            $table->string('method')->nullable(); // drip|sprinkler|flood|manual
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('volume_liters', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irrigation_logs');
        Schema::dropIfExists('soil_test_records');
    }
};
