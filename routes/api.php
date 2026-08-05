<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * The public OPES AGRI OS API. URI-versioned (`/api/v1/...`); a breaking
 * change gets a new prefix rather than a change under this one. Every route
 * here is token-authenticated (Sanctum) and company-scoped — see
 * App\Http\Middleware\ResolveApiCompany, which turns the token's bound
 * company into App\Support\CurrentCompany before anything else runs, the
 * same way SetCurrentCompany does for the session-authenticated web app.
 */
Route::prefix('v1')->middleware(['auth:sanctum', 'api.company', 'throttle:api'])->group(function () {
    Route::get('/ping', function (Request $request) {
        return response()->json([
            'data' => [
                'message' => 'pong',
                'company' => $request->user()->currentAccessToken()->company->slug,
            ],
        ]);
    })->name('api.v1.ping');
});
