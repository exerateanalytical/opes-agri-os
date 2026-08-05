<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Farm Management + Land Management (basic). See
     * docs/architecture/agri-platform-roadmap.md — `boundary` is plain JSON
     * (a GeoJSON-shaped array of coordinates), not a MySQL spatial type:
     * storage and a plain map display is all V1 needs, no spatial queries.
     */
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location_text')->nullable();
            $table->decimal('size_hectares', 10, 2)->nullable();
            $table->string('ownership_type')->default('owned'); // owned|leased|mixed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'name']);
        });

        Schema::create('fields', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('farm_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('area_hectares', 10, 2)->nullable();
            $table->json('boundary')->nullable();
            $table->decimal('centroid_lat', 10, 7)->nullable();
            $table->decimal('centroid_lng', 10, 7)->nullable();
            $table->string('ownership_type')->default('owned'); // owned|leased
            $table->date('lease_start')->nullable();
            $table->date('lease_end')->nullable();
            $table->text('lease_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'farm_id']);
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('fields');
        Schema::dropIfExists('farms');
    }
};
