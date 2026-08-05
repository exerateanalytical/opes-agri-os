<?php

use App\Domain\Agri\Http\Controllers\Api\V1\CropCycleController;
use App\Domain\Agri\Http\Controllers\Api\V1\FarmController;
use App\Domain\Agri\Http\Controllers\Api\V1\FieldController;
use App\Domain\Agri\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Domain\Agri\Http\Controllers\Api\V1\SeasonController;
use App\Domain\Cooperative\Http\Controllers\Api\V1\CooperativeMeetingController;
use App\Domain\Cooperative\Http\Controllers\Api\V1\CooperativeMemberController;
use App\Domain\Cooperative\Http\Controllers\Api\V1\CooperativeVoteController;
use App\Domain\Cooperative\Http\Controllers\Api\V1\LoanController;
use App\Domain\Livestock\Http\Controllers\Api\V1\AnimalBatchController;
use App\Domain\Livestock\Http\Controllers\Api\V1\AnimalController;
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
        Route::get('/fields/{field}/soil-tests', [FieldController::class, 'soilTests'])->name('api.v1.fields.soil-tests.index');
        Route::get('/fields/{field}/irrigation-logs', [FieldController::class, 'irrigationLogs'])->name('api.v1.fields.irrigation-logs.index');
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
    Route::post('/fields/{field}/soil-tests', [FieldController::class, 'recordSoilTest'])
        ->middleware('abilities:farms.record-soil-test')->name('api.v1.fields.soil-tests.store');
    Route::post('/fields/{field}/irrigation-logs', [FieldController::class, 'recordIrrigation'])
        ->middleware('abilities:farms.record-irrigation')->name('api.v1.fields.irrigation-logs.store');

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

    Route::middleware('abilities:procurement.view')->group(function () {
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('api.v1.purchase-orders.index');
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('api.v1.purchase-orders.show');
    });
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('abilities:procurement.create')->name('api.v1.purchase-orders.store');
    Route::patch('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->middleware('abilities:procurement.update')->name('api.v1.purchase-orders.update');
    Route::post('/purchase-orders/{purchaseOrder}/issue', [PurchaseOrderController::class, 'issue'])
        ->middleware('abilities:procurement.issue')->name('api.v1.purchase-orders.issue');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
        ->middleware('abilities:procurement.receive')->name('api.v1.purchase-orders.receive');

    Route::middleware('abilities:livestock.view')->group(function () {
        Route::get('/animals', [AnimalController::class, 'index'])->name('api.v1.animals.index');
        Route::get('/animals/{animal}', [AnimalController::class, 'show'])->name('api.v1.animals.show');
    });
    Route::post('/animals', [AnimalController::class, 'store'])
        ->middleware('abilities:livestock.create')->name('api.v1.animals.store');
    Route::patch('/animals/{animal}', [AnimalController::class, 'update'])
        ->middleware('abilities:livestock.update')->name('api.v1.animals.update');
    Route::delete('/animals/{animal}', [AnimalController::class, 'destroy'])
        ->middleware('abilities:livestock.delete')->name('api.v1.animals.destroy');
    Route::post('/animals/{animal}/health-records', [AnimalController::class, 'recordHealth'])
        ->middleware('abilities:livestock.record-health')->name('api.v1.animals.health-records.store');
    Route::post('/animals/{animal}/production-records', [AnimalController::class, 'recordProduction'])
        ->middleware('abilities:livestock.record-production')->name('api.v1.animals.production-records.store');

    Route::middleware('abilities:livestock.view')->group(function () {
        Route::get('/animal-batches', [AnimalBatchController::class, 'index'])->name('api.v1.animal-batches.index');
        Route::get('/animal-batches/{animalBatch}', [AnimalBatchController::class, 'show'])->name('api.v1.animal-batches.show');
    });
    Route::post('/animal-batches', [AnimalBatchController::class, 'store'])
        ->middleware('abilities:livestock.create')->name('api.v1.animal-batches.store');
    Route::patch('/animal-batches/{animalBatch}', [AnimalBatchController::class, 'update'])
        ->middleware('abilities:livestock.update')->name('api.v1.animal-batches.update');
    Route::delete('/animal-batches/{animalBatch}', [AnimalBatchController::class, 'destroy'])
        ->middleware('abilities:livestock.delete')->name('api.v1.animal-batches.destroy');
    Route::post('/animal-batches/{animalBatch}/adjust-count', [AnimalBatchController::class, 'adjustCount'])
        ->middleware('abilities:livestock.update')->name('api.v1.animal-batches.adjust-count');

    Route::middleware('abilities:cooperative.view')->group(function () {
        Route::get('/cooperative-members', [CooperativeMemberController::class, 'index'])->name('api.v1.cooperative-members.index');
        Route::get('/cooperative-members/{cooperativeMember}', [CooperativeMemberController::class, 'show'])->name('api.v1.cooperative-members.show');
    });
    Route::post('/cooperative-members', [CooperativeMemberController::class, 'store'])
        ->middleware('abilities:cooperative.create')->name('api.v1.cooperative-members.store');
    Route::patch('/cooperative-members/{cooperativeMember}', [CooperativeMemberController::class, 'update'])
        ->middleware('abilities:cooperative.update')->name('api.v1.cooperative-members.update');
    Route::delete('/cooperative-members/{cooperativeMember}', [CooperativeMemberController::class, 'destroy'])
        ->middleware('abilities:cooperative.delete')->name('api.v1.cooperative-members.destroy');
    Route::post('/cooperative-members/{cooperativeMember}/contributions', [CooperativeMemberController::class, 'recordContribution'])
        ->middleware('abilities:cooperative.record-contribution')->name('api.v1.cooperative-members.contributions.store');

    Route::middleware('abilities:cooperative.view')->group(function () {
        Route::get('/loans', [LoanController::class, 'index'])->name('api.v1.loans.index');
        Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('api.v1.loans.show');
    });
    Route::post('/loans', [LoanController::class, 'store'])
        ->middleware('abilities:cooperative.create')->name('api.v1.loans.store');
    Route::post('/loans/{loan}/disburse', [LoanController::class, 'disburse'])
        ->middleware('abilities:cooperative.disburse-loan')->name('api.v1.loans.disburse');
    Route::post('/loans/{loan}/repayments', [LoanController::class, 'recordRepayment'])
        ->middleware('abilities:cooperative.record-repayment')->name('api.v1.loans.repayments.store');

    Route::middleware('abilities:cooperative.view')->group(function () {
        Route::get('/cooperative-meetings', [CooperativeMeetingController::class, 'index'])->name('api.v1.cooperative-meetings.index');
        Route::get('/cooperative-meetings/{cooperativeMeeting}', [CooperativeMeetingController::class, 'show'])->name('api.v1.cooperative-meetings.show');
        Route::get('/cooperative-votes', [CooperativeVoteController::class, 'index'])->name('api.v1.cooperative-votes.index');
        Route::get('/cooperative-votes/{cooperativeVote}', [CooperativeVoteController::class, 'show'])->name('api.v1.cooperative-votes.show');
    });
    Route::post('/cooperative-meetings', [CooperativeMeetingController::class, 'store'])
        ->middleware('abilities:cooperative.create')->name('api.v1.cooperative-meetings.store');
    Route::patch('/cooperative-meetings/{cooperativeMeeting}', [CooperativeMeetingController::class, 'update'])
        ->middleware('abilities:cooperative.update')->name('api.v1.cooperative-meetings.update');
    Route::post('/cooperative-meetings/{cooperativeMeeting}/attendance', [CooperativeMeetingController::class, 'recordAttendance'])
        ->middleware('abilities:cooperative.record-attendance')->name('api.v1.cooperative-meetings.attendance.store');

    Route::post('/cooperative-votes', [CooperativeVoteController::class, 'store'])
        ->middleware('abilities:cooperative.create')->name('api.v1.cooperative-votes.store');
    Route::post('/cooperative-votes/{cooperativeVote}/close', [CooperativeVoteController::class, 'close'])
        ->middleware('abilities:cooperative.update')->name('api.v1.cooperative-votes.close');
    Route::post('/cooperative-votes/{cooperativeVote}/ballots', [CooperativeVoteController::class, 'castVote'])
        ->middleware('abilities:cooperative.cast-vote')->name('api.v1.cooperative-votes.ballots.store');
});
