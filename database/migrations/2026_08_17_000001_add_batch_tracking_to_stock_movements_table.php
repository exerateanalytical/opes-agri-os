<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive and nullable: a movement recorded before this migration, or
     * one written by Sales (which has no notion of a batch), simply has both
     * as NULL — "no batch" is the correct reading for retail goods, not a
     * gap that needs backfilling. Recording only, not enforcing: nothing in
     * V1 does batch-aware (FEFO) consumption at issue time. See
     * docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('reason');
            $table->date('expires_on')->nullable()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['batch_number', 'expires_on']);
        });
    }
};
