<?php

use App\Modules\Property\Http\Controllers\PropertySettingsController;
use Illuminate\Support\Facades\Route;

/*
| Property settings (SYS-03). These act on the property in the URL, so they do not need X-Property-Id.
*/

Route::middleware('auth:sanctum')->prefix('settings')->name('settings.')->group(function () {
    Route::get('properties', [PropertySettingsController::class, 'index'])->name('properties.index');
    Route::post('properties', [PropertySettingsController::class, 'store'])->name('properties.store');
    Route::get('properties/{property}', [PropertySettingsController::class, 'show'])->name('properties.show');
    Route::patch('properties/{property}', [PropertySettingsController::class, 'update'])->name('properties.update');
});
