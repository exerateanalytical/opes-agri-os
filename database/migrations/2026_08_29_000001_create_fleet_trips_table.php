<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fleet & Logistics (V4 Phase 3a) — a trip log against a FixedAsset,
     * the same dated-record shape as AssetMaintenanceRecord: a vehicle is
     * a fixed asset with a trip log, not a new concept. See
     * docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::create('fleet_trips', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->string('driver_name')->nullable();
            $table->string('purpose')->nullable();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->decimal('start_odometer', 10, 1)->nullable();
            $table->decimal('end_odometer', 10, 1)->nullable();
            $table->decimal('fuel_cost', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'fixed_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_trips');
    }
};
