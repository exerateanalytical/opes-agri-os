<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Turns any exception raised on an `/api/v1/*` request into the Stripe-style
 * error envelope — `{"error": {"type", "code", "message", "details"?}}` — so
 * a client never has to sniff Laravel's default HTML/validation shapes.
 *
 * Scoped to `api/v1/*` specifically, not `api/*` — the older `api/sync/v1/*`
 * routes (SyncController) are a separate, already-shipped protocol with their
 * own response conventions (a device depends on that shape exactly as it is)
 * and must not be touched by this renderer.
 *
 * A plain `RuntimeException` bubbling up from a service (DocumentIssuer,
 * DocumentConverter, PaymentRecorder, ...) is treated as a state conflict —
 * "already void", "exceeds the outstanding balance" — rather than a 422: the
 * request was well-formed, the record just isn't in a state that allows it.
 * Checked last among the typed cases: Symfony's HttpException (what `abort()`
 * throws) is itself a RuntimeException, so anything that already carries a
 * real HTTP status — 403 from an `abort_unless()`, 404, 429 — must be matched
 * first or its intended status gets overwritten by the generic 409.
 */
class ApiExceptionRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/v1/*')) {
            return null;
        }

        [$status, $type, $code, $message, $details] = match (true) {
            $e instanceof ValidationException => [
                422, 'invalid_request', 'validation_failed', 'The given data was invalid.', $e->errors(),
            ],
            $e instanceof AuthenticationException => [
                401, 'authentication_error', 'unauthenticated', 'Authentication is required.', null,
            ],
            $e instanceof AuthorizationException => [
                403, 'permission_error', 'insufficient_ability', $e->getMessage() ?: 'This action is not authorized.', null,
            ],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [
                404, 'invalid_request', 'not_found', 'The requested resource could not be found.', null,
            ],
            $e instanceof TooManyRequestsHttpException => [
                429, 'rate_limit_error', 'rate_limited', 'Too many requests.', null,
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(), 'api_error', 'http_error', $e->getMessage() ?: 'Request failed.', null,
            ],
            $e instanceof RuntimeException => [
                409, 'domain_conflict', 'conflict', $e->getMessage(), null,
            ],
            default => [
                500,
                'api_error',
                'internal_error',
                app()->hasDebugModeEnabled() ? $e->getMessage() : 'An unexpected error occurred.',
                null,
            ],
        };

        $error = ['type' => $type, 'code' => $code, 'message' => $message];

        if ($details !== null) {
            $error['details'] = $details;
        }

        $response = response()->json(['error' => $error], $status);

        if ($e instanceof TooManyRequestsHttpException && $e->getHeaders()['Retry-After'] ?? null) {
            $response->header('Retry-After', $e->getHeaders()['Retry-After']);
        }

        return $response;
    }
}
