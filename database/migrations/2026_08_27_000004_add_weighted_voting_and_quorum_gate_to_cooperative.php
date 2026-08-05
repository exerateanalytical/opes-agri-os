<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Governance V3 M3 follow-up, resolving the two questions its own
     * commit flagged as deliberately out (see
     * docs/architecture/agri-platform-roadmap.md):
     *
     * - Weighted voting. `CooperativeMember.vote_weight` is a bylaws-set
     *   number (share-weighted, patronage-weighted, or left at the default
     *   of 1 for one-member-one-vote), and `CooperativeVote.weighted`
     *   decides per-resolution whether a tally reads weight or a plain
     *   headcount — a cooperative can run one-member-one-vote for most
     *   business and weighted for a capital resolution its bylaws single
     *   out, without every vote being forced the same way.
     * - Quorum as a hard gate. No schema change needed for this half: a vote
     *   tied to a meeting whose quorum is not met is refused at open time
     *   (`CooperativeVoteController::store()`), reading `quorum_required`
     *   and `MeetingAttendance` that already exist — a vote raised without
     *   a meeting, which `CooperativeMeeting::quorumMet()` has nothing to
     *   check, is unaffected, exactly as before.
     */
    public function up(): void
    {
        Schema::table('cooperative_members', function (Blueprint $table) {
            // Bylaws-set weight for a weighted vote; 1 keeps a member at an
            // ordinary one-member-one-vote reading until a cooperative's
            // bylaws say otherwise.
            $table->decimal('vote_weight', 12, 2)->default(1)->after('balance');
        });

        Schema::table('cooperative_votes', function (Blueprint $table) {
            // Whether this resolution's tally reads member weight or a
            // plain headcount. Default false: existing votes keep reading
            // exactly as they did before this column existed.
            $table->boolean('weighted')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_votes', function (Blueprint $table) {
            $table->dropColumn('weighted');
        });

        Schema::table('cooperative_members', function (Blueprint $table) {
            $table->dropColumn('vote_weight');
        });
    }
};
