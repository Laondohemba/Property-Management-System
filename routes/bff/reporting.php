<?php

use App\Modules\Reporting\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'property'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});
