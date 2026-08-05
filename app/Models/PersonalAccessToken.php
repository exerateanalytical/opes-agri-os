<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum's token model, extended with the one thing every API token here
 * needs beyond Sanctum's defaults: which company it acts on. A user can
 * belong to several companies, so that can never be inferred from the token
 * holder alone — see App\Http\Middleware\ResolveApiCompany, which is the only
 * place this column is read at request time.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
