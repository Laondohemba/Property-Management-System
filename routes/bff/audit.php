<?php

use App\Modules\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

/*
| Audit trail viewer (AUD-03). X-Property-Id is optional: Super Admins can view the whole organisation.
*/

Route::middleware(['auth:sanctum', 'property:optional'])->group(function () {
    Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');
});
