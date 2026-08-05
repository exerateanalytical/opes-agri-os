<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Livestock Management (V2). An animal is its own model, not a
     * FixedAsset subtype — it breeds, gets sick and dies, none of which fits
     * depreciation-schedule semantics, the same reasoning that kept a crop
     * cycle out of the Item catalogue directly. See
     * docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::create('animals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            // Optional: a business can run livestock without Farms switched on.
            $table->foreignUlid('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tag_number')->nullable();
            $table->string('species'); // e.g. cattle, goat, poultry
            $table->string('breed')->nullable();
            $table->string('sex')->nullable(); // male|female
            $table->date('date_of_birth')->nullable();
            $table->string('status')->default('active'); // active|sold|deceased|culled
            $table->date('acquired_on')->nullable();
            $table->decimal('acquisition_cost', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'farm_id']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('animal_health_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('animal_id')->constrained()->cascadeOnDelete();
            $table->string('record_type'); // vaccination|treatment|checkup|illness
            $table->string('description');
            $table->date('administered_on');
            $table->date('next_due_on')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'animal_id']);
        });

        Schema::create('animal_production_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('animal_id')->constrained()->cascadeOnDelete();
            // What was produced (milk, eggs, wool) — an Item, the same
            // reasoning CropCycle uses so it's stock the business already
            // knows how to sell.
            $table->foreignUlid('item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('unit')->nullable();
            $table->date('recorded_on');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'animal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_production_records');
        Schema::dropIfExists('animal_health_records');
        Schema::dropIfExists('animals');
    }
};
