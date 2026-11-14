<?php

use App\Http\Controllers\SpaController;
use App\Modules\Identity\Http\Controllers\AcceptInvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Session authentication extras
|--------------------------------------------------------------------------
| Fortify registers /login, /logout, /two-factor-challenge, /forgot-password,
| /reset-password and /user/*. Accepting a staff invitation sits alongside them.
*/

Route::post('/accept-invitation', AcceptInvitationController::class)
    ->middleware('throttle:6,1')
    ->name('invitation.accept');

/*
|--------------------------------------------------------------------------
| SPA entry point
|--------------------------------------------------------------------------
| In production Nginx serves public/index.html for deep links (spec §11.2).
| These routes give the same behaviour under `php artisan serve`. BFF routes
| live in routes/bff/*.php.
*/

Route::get('/', SpaController::class);
Route::fallback(SpaController::class);
