<?php

use App\Modules\Identity\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

/*
| Identity BFF routes. Prefix /bff and the "bff" middleware group are applied in bootstrap/app.php.
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('session', SessionController::class)->name('session');
});
