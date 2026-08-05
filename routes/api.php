<?php

use App\Domain\Agri\Http\Controllers\Api\V1\CropCycleController;
use App\Domain\Agri\Http\Controllers\Api\V1\FarmController;
use App\Domain\Agri\Http\Controllers\Api\V1\FieldController;
use App\Domain\Agri\Http\Controllers\Api\V1\SeasonController;
use App\Domain\Sales\Http\Controllers\Api\V1\ContactController;
use App\Domain\Sales\Http\Controllers\Api\V1\DocumentController;
use App\Domain\Sales\Http\Controllers\Api\V1\DocumentLifecycleController;
use App\Domain\Sales\Http\Controllers\Api\V1\ItemController;
use App\Domain\Sales\Http\Controllers\Api\V1\PaymentController;
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

    Route::middleware('abilities:sales.view')->group(function () {
        Route::get('/documents', [DocumentController::class, 'index'])->name('api.v1.documents.index');
        Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('api.v1.documents.show');
    });
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('abilities:sales.create')->name('api.v1.documents.store');
    Route::patch('/documents/{document}', [DocumentController::class, 'update'])
        ->middleware('abilities:sales.update')->name('api.v1.documents.update');
    Route::post('/documents/{document}/issue', [DocumentLifecycleController::class, 'issue'])
        ->middleware('abilities:sales.issue')->name('api.v1.documents.issue');
    Route::post('/documents/{document}/void', [DocumentLifecycleController::class, 'void'])
        ->middleware('abilities:sales.void')->name('api.v1.documents.void');
    Route::post('/documents/{document}/convert', [DocumentLifecycleController::class, 'convert'])
        ->middleware('abilities:sales.create')->name('api.v1.documents.convert');
    Route::post('/documents/{document}/payments', [PaymentController::class, 'store'])
        ->middleware('abilities:payments.record')->name('api.v1.documents.payments.store');

    Route::middleware('abilities:payments.view')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('api.v1.payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('api.v1.payments.show');
    });

    Route::middleware('abilities:farms.view')->group(function () {
        Route::get('/farms', [FarmController::class, 'index'])->name('api.v1.farms.index');
        Route::get('/farms/{farm}', [FarmController::class, 'show'])->name('api.v1.farms.show');
        Route::get('/fields', [FieldController::class, 'index'])->name('api.v1.fields.index');
        Route::get('/fields/{field}', [FieldController::class, 'show'])->name('api.v1.fields.show');
        Route::get('/seasons', [SeasonController::class, 'index'])->name('api.v1.seasons.index');
        Route::get('/seasons/{season}', [SeasonController::class, 'show'])->name('api.v1.seasons.show');
    });
    Route::middleware('abilities:farms.create')->group(function () {
        Route::post('/farms', [FarmController::class, 'store'])->name('api.v1.farms.store');
        Route::post('/fields', [FieldController::class, 'store'])->name('api.v1.fields.store');
        Route::post('/seasons', [SeasonController::class, 'store'])->name('api.v1.seasons.store');
    });
    Route::middleware('abilities:farms.update')->group(function () {
        Route::patch('/farms/{farm}', [FarmController::class, 'update'])->name('api.v1.farms.update');
        Route::patch('/fields/{field}', [FieldController::class, 'update'])->name('api.v1.fields.update');
        Route::patch('/seasons/{season}', [SeasonController::class, 'update'])->name('api.v1.seasons.update');
    });
    Route::middleware('abilities:farms.delete')->group(function () {
        Route::delete('/farms/{farm}', [FarmController::class, 'destroy'])->name('api.v1.farms.destroy');
        Route::delete('/fields/{field}', [FieldController::class, 'destroy'])->name('api.v1.fields.destroy');
        Route::delete('/seasons/{season}', [SeasonController::class, 'destroy'])->name('api.v1.seasons.destroy');
    });

    Route::middleware('abilities:crops.view')->group(function () {
        Route::get('/crop-cycles', [CropCycleController::class, 'index'])->name('api.v1.crop-cycles.index');
        Route::get('/crop-cycles/{cropCycle}', [CropCycleController::class, 'show'])->name('api.v1.crop-cycles.show');
    });
    Route::post('/crop-cycles', [CropCycleController::class, 'store'])
        ->middleware('abilities:crops.create')->name('api.v1.crop-cycles.store');
    Route::patch('/crop-cycles/{cropCycle}', [CropCycleController::class, 'update'])
        ->middleware('abilities:crops.update')->name('api.v1.crop-cycles.update');
    Route::post('/crop-cycles/{cropCycle}/harvest', [CropCycleController::class, 'harvest'])
        ->middleware('abilities:crops.record-harvest')->name('api.v1.crop-cycles.harvest');
});
