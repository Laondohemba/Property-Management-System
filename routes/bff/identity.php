<?php

use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\SessionController;
use App\Modules\Identity\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

/*
| Identity BFF routes. Prefix /bff and the "bff" middleware group are applied in bootstrap/app.php.
*/

Route::middleware(['auth:sanctum', 'property:default'])->group(function () {
    Route::get('session', SessionController::class)->name('session');
});

// Staff and role administration (SYS-01, SYS-02). These act on the property in the URL, not X-Property-Id.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('users', [StaffController::class, 'index'])->name('users.index');
    Route::post('users', [StaffController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [StaffController::class, 'show'])->name('users.show');
    Route::patch('users/{user}', [StaffController::class, 'update'])->name('users.update');
    Route::put('users/{user}/properties/{property}', [StaffController::class, 'assign'])->name('users.assign');
    Route::delete('users/{user}/properties/{property}', [StaffController::class, 'unassign'])->name('users.unassign');
    Route::post('users/{user}/invitation', [StaffController::class, 'resendInvitation'])->name('users.invitation');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});
