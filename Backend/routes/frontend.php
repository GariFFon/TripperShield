<?php

use App\Http\Controllers\Frontend\PolicyFrontendController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PolicyFrontendController::class, 'index'])->name('frontend.index');

Route::get('/policy/create', [PolicyFrontendController::class, 'create'])->name('frontend.create');
Route::redirect('/policies/create', '/policy/create');
Route::post('/policy', [PolicyFrontendController::class, 'store'])->name('frontend.store');

Route::get('/policies', [PolicyFrontendController::class, 'dashboard'])->name('frontend.dashboard');
Route::redirect('/dashboard', '/policies');
Route::get('/policy/{id}', [PolicyFrontendController::class, 'show'])->name('frontend.show');

Route::post('/policy/{id}/submit', [PolicyFrontendController::class, 'submit'])->name('frontend.submit');
Route::post('/policy/{id}/reconcile', [PolicyFrontendController::class, 'reconcile'])->name('frontend.reconcile');
Route::post('/policy/{id}/retry', [PolicyFrontendController::class, 'retry'])->name('frontend.retry');

Route::post('/policy/calculate-premium', [PolicyFrontendController::class, 'calculatePremium'])->name('frontend.calculate-premium');