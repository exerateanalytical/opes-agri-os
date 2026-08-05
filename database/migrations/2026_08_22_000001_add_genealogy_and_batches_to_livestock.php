<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Livestock V2 M2: breeding/genealogy links on Animal, and a separate
     * AnimalBatch model for flock-style counted tracking — a poultry flock
     * is recorded as a count, not as individually tagged animals, so it
     * gets its own table rather than forcing one `animals` row per bird.
     */
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->foreignUlid('sire_id')->nullable()->after('sex')->constrained('animals')->nullOnDelete();
            $table->foreignUlid('dam_id')->nullable()->after('sire_id')->constrained('animals')->nullOnDelete();
        });

        Schema::create('animal_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->string('species'); // e.g. broiler, layer
            $table->string('breed')->nullable();
            $table->unsignedInteger('initial_count');
            $table->unsignedInteger('current_count');
            $table->date('acquired_on')->nullable();
            $table->string('status')->default('active'); // active|sold|closed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'farm_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_batches');

        Schema::table('animals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dam_id');
            $table->dropConstrainedForeignId('sire_id');
        });
    }
};
