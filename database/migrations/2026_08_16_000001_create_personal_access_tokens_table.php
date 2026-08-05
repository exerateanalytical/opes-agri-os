<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sanctum's standard schema plus `company_id`: every API token this app
     * issues is bound to exactly one company at mint time (a user can belong to
     * several, so the token — not the user — is what says which one an API
     * request acts on). See App\Http\Middleware\ResolveApiCompany.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // `morphs()` above already indexes (tokenable_type, tokenable_id);
            // this covers "every token this company has issued" (the API Keys
            // settings page) without repeating those two columns.
            $table->index('company_id', 'personal_access_tokens_company_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
