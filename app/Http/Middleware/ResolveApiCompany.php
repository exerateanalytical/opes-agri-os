<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API's equivalent of SetCurrentCompany, for token-authenticated requests.
 *
 * A session knows "the current company" from the user's `current_company_id`;
 * a token has no such thing — a user can belong to several companies, so the
 * token itself carries which one it was minted for (`company_id` on
 * personal_access_tokens). This re-checks membership and suspension on every
 * request rather than trusting the column, exactly as SetCurrentCompany does,
 * so revoking access or suspending a business takes effect immediately even
 * for a token that is otherwise still valid.
 */
class ResolveApiCompany
{
    public function __construct(private readonly CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user === null || $token === null || ! $token->company_id) {
            return $this->deny('token_company_mismatch', 'This token is not bound to a company.');
        }

        $company = Company::find($token->company_id);

        if ($company === null || ! $user->belongsToCompany($company)) {
            return $this->deny('token_company_mismatch', 'This token\'s company is no longer accessible to this user.');
        }

        if ($company->isSuspended()) {
            return $this->deny('company_suspended', 'This business account is suspended.');
        }

        $this->current->set($company);

        return $next($request);
    }

    protected function deny(string $code, string $message): Response
    {
        return response()->json([
            'error' => [
                'type' => 'permission_error',
                'code' => $code,
                'message' => $message,
            ],
        ], 403);
    }
}
