<?php

use App\Domain\Sales\Http\Controllers\Api\V1\ContactController;
use App\Domain\Sales\Http\Controllers\Api\V1\ItemController;
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

    /*
     * Every action carries its own Sanctum ability (what the TOKEN may do)
     * on top of the Policy check inside the controller (what the USER may do
     * to that specific record) — see App\Http\Controllers\Api\V1\Controller.
     */
    Route::middleware('abilities:customers.view')->group(function () {
        Route::get('/contacts', [ContactController::class, 'index'])->name('api.v1.contacts.index');
        Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('api.v1.contacts.show');
    });
    Route::post('/contacts', [ContactController::class, 'store'])
        ->middleware('abilities:customers.create')->name('api.v1.contacts.store');
    Route::patch('/contacts/{contact}', [ContactController::class, 'update'])
        ->middleware('abilities:customers.update')->name('api.v1.contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
        ->middleware('abilities:customers.delete')->name('api.v1.contacts.destroy');

    Route::middleware('abilities:products.view')->group(function () {
        Route::get('/items', [ItemController::class, 'index'])->name('api.v1.items.index');
        Route::get('/items/{item}', [ItemController::class, 'show'])->name('api.v1.items.show');
    });
    Route::post('/items', [ItemController::class, 'store'])
        ->middleware('abilities:products.create')->name('api.v1.items.store');
    Route::patch('/items/{item}', [ItemController::class, 'update'])
        ->middleware('abilities:products.update')->name('api.v1.items.update');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])
        ->middleware('abilities:products.delete')->name('api.v1.items.destroy');
});
