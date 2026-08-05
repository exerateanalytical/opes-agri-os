<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use RuntimeException;

/**
 * Mints an API token bound to one company.
 *
 * `User::createToken()` (Sanctum's own) can't be used as-is: its insert never
 * sets `company_id`, and that column is not nullable — a token with no company
 * is exactly the case ResolveApiCompany exists to refuse, so it must not be
 * possible to create one. This also enforces the other half of "API as a
 * product done safely": a token can only be minted with abilities the issuing
 * user actually holds in that company, never whatever the client asks for.
 */
class ApiTokenIssuer
{
    /**
     * @param  array<int, string>  $abilities  Permission slugs (e.g. "sales.issue"),
     *                                         or ["*"] for everything the user holds.
     */
    public function issue(
        User $user,
        Company $company,
        string $name,
        array $abilities,
        ?CarbonInterface $expiresAt = null,
    ): NewAccessToken {
        if (! $user->belongsToCompany($company)) {
            throw new RuntimeException('A token can only be issued for a company you belong to.');
        }

        foreach ($abilities as $ability) {
            if ($ability !== '*' && ! $user->hasPermissionIn($company, $ability)) {
                throw new RuntimeException("You cannot grant an ability you do not hold yourself: {$ability}.");
            }
        }

        $plainTextToken = Str::random(40);

        $token = new PersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'company_id' => $company->id,
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ])->save();

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }
}
