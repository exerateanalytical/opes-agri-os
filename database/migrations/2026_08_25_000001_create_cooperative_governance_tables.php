<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cooperative governance (V3 M3) — meetings, who attended, and the
     * resolutions members vote on. Membership rights, not cash, which is
     * why nothing here touches the accounting ledger the way loans (V3 M2)
     * do. See docs/architecture/agri-platform-roadmap.md.
     */
    public function up(): void
    {
        Schema::create('cooperative_meetings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('scheduled_on');
            // How many attendees make this meeting able to decide anything.
            // Zero is a legitimate answer for a business that doesn't track
            // quorum rules and just wants a record of who showed up.
            $table->unsignedInteger('quorum_required')->default(0);
            $table->string('status')->default('scheduled'); // scheduled|held|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('cooperative_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('cooperative_member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cooperative_meeting_id', 'cooperative_member_id'], 'meeting_attendances_meeting_member_unique');
        });

        Schema::create('cooperative_votes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            // Optional: a resolution can be raised and voted on asynchronously,
            // without ever being tied to a specific sitting.
            $table->foreignUlid('cooperative_meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open|closed
            $table->date('opened_on');
            $table->date('closed_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        Schema::create('vote_ballots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('cooperative_vote_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('cooperative_member_id')->constrained()->cascadeOnDelete();
            $table->string('choice'); // for|against|abstain
            $table->timestamps();

            // One member, one ballot per vote — cast() relies on this to
            // refuse a second vote rather than checking it in application
            // code alone.
            $table->unique(['cooperative_vote_id', 'cooperative_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_ballots');
        Schema::dropIfExists('cooperative_votes');
        Schema::dropIfExists('meeting_attendances');
        Schema::dropIfExists('cooperative_meetings');
    }
};
