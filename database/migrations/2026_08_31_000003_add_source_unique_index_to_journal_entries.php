<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `Ledger::entryFor()` is what makes posting idempotent per source, but
     * until now that was only ever an application-level check-then-insert —
     * two concurrent calls for the same source could both pass the check
     * before either had inserted, and both post. A unique index on
     * (company_id, source_type, source_id) makes the database itself refuse
     * the second insert, and `Ledger::post()` catches that and returns the
     * entry that won the race instead of throwing, so a retry stays
     * harmless under real concurrency, not just under a single request.
     *
     * No partial/filtered index is needed for the many entries with no
     * source at all: MySQL treats every NULL in a unique index as distinct
     * from every other NULL, so any number of sourceless entries can
     * coexist — only a genuine (company_id, source_type, source_id) repeat
     * is rejected.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unique(['company_id', 'source_type', 'source_id'], 'journal_entries_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_source_unique');
        });
    }
};
