<?php

use App\Http\Controllers\Api\PolicyRequestController;
use App\Http\Controllers\Api\MockInsurerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Policy Request endpoints
Route::prefix('policy-requests')->group(function () {
    Route::get('/', [PolicyRequestController::class, 'index']);
    Route::post('/', [PolicyRequestController::class, 'store']);
    Route::get('/{id}', [PolicyRequestController::class, 'show']);
    Route::post('/{id}/submit', [PolicyRequestController::class, 'submit']);
});

// Mock Insurer endpoints
Route::prefix('mock-insurer')->group(function () {
    Route::get('/scenarios', [MockInsurerController::class, 'scenarios']);
    Route::get('/status/{providerReference}', [MockInsurerController::class, 'status']);
    Route::post('/callback', [MockInsurerController::class, 'callback']);
    Route::post('/test/duplicate-callback', [MockInsurerController::class, 'testDuplicateCallback']);
});

// Admin/Reconciliation endpoints
Route::prefix('admin')->group(function () {
    Route::post('/reconcile', [MockInsurerController::class, 'reconcile']);
});