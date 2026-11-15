<?php

use App\Modules\Billing\Http\Controllers\TaxController;
use Illuminate\Support\Facades\Route;

/*
| Taxes and fees of the active property (spec §2.8). Requires X-Property-Id.
*/

Route::middleware(['auth:sanctum', 'property'])->prefix('settings')->name('settings.')->group(function () {
    Route::get('taxes', [TaxController::class, 'index'])->name('taxes.index');
    Route::post('taxes', [TaxController::class, 'store'])->name('taxes.store');
    Route::patch('taxes/{tax}', [TaxController::class, 'update'])->name('taxes.update');
});
