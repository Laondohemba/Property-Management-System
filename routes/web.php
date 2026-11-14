<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA entry point
|--------------------------------------------------------------------------
| In production Nginx serves public/index.html for deep links (spec §11.2).
| These routes give the same behaviour under `php artisan serve`. Session
| authentication routes (/login, /logout, /two-factor-challenge, ...) are
| registered by Fortify; BFF routes live in routes/bff/*.php.
*/

Route::get('/', SpaController::class);
Route::fallback(SpaController::class);
