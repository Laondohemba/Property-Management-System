<?php

use App\Modules\Rates\Http\Controllers\PolicyController;
use App\Modules\Rates\Http\Controllers\RatePlanController;
use Illuminate\Support\Facades\Route;

/*
| Rate plans (RTE-01) and their cancellation and deposit policies (RTE-07).
| Requires X-Property-Id.
*/

Route::middleware(['auth:sanctum', 'property'])->group(function () {
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('policies', [PolicyController::class, 'index'])->name('policies.index');
        Route::post('cancellation-policies', [PolicyController::class, 'storeCancellation'])->name('cancellation-policies.store');
        Route::patch('cancellation-policies/{cancellationPolicy}', [PolicyController::class, 'updateCancellation'])->name('cancellation-policies.update');
        Route::post('deposit-policies', [PolicyController::class, 'storeDeposit'])->name('deposit-policies.store');
        Route::patch('deposit-policies/{depositPolicy}', [PolicyController::class, 'updateDeposit'])->name('deposit-policies.update');
    });

    Route::get('rate-plans', [RatePlanController::class, 'index'])->name('rate-plans.index');
    Route::get('rate-plans/options', [RatePlanController::class, 'options'])->name('rate-plans.options');
    Route::post('rate-plans', [RatePlanController::class, 'store'])->name('rate-plans.store');
    Route::get('rate-plans/{ratePlan}', [RatePlanController::class, 'show'])->name('rate-plans.show');
    Route::patch('rate-plans/{ratePlan}', [RatePlanController::class, 'update'])->name('rate-plans.update');
    Route::get('rate-plans/{ratePlan}/preview', [RatePlanController::class, 'preview'])->name('rate-plans.preview');
});
