<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshots each ballot's member weight at cast time, so a weighted
     * vote's tally() sums what a member's weight *was* when they voted
     * rather than re-reading `cooperative_members.vote_weight` live — a
     * member's weight changing after casting (or after the vote closed)
     * must never retroactively change the outcome. Nullable with no
     * backfill: this is pre-production data, and `tally()` reads a null
     * as the implicit weight of 1 every existing ballot already carried.
     */
    public function up(): void
    {
        Schema::table('vote_ballots', function (Blueprint $table) {
            $table->decimal('weight_at_cast', 12, 2)->nullable()->after('choice');
        });
    }

    public function down(): void
    {
        Schema::table('vote_ballots', function (Blueprint $table) {
            $table->dropColumn('weight_at_cast');
        });
    }
};
