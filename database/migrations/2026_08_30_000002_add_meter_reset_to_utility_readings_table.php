<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a reading as taken against a meter that was physically replaced
     * or reset since the previous reading, so a lower `meter_reading` than
     * the last one on file is expected rather than a data-entry mistake.
     * See docs/architecture/agri-platform-roadmap.md — Hardening note.
     */
    public function up(): void
    {
        Schema::table('utility_readings', function (Blueprint $table) {
            $table->boolean('meter_reset')->default(false)->after('meter_reading');
        });
    }

    public function down(): void
    {
        Schema::table('utility_readings', function (Blueprint $table) {
            $table->dropColumn('meter_reset');
        });
    }
};
