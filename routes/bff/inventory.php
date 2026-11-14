<?php

use App\Modules\Inventory\Http\Controllers\AmenityController;
use App\Modules\Inventory\Http\Controllers\RoomController;
use App\Modules\Inventory\Http\Controllers\RoomTypeController;
use Illuminate\Support\Facades\Route;

/*
| Rooms, room types and amenities of the active property (SYS-04). Requires X-Property-Id.
*/

Route::middleware(['auth:sanctum', 'property'])->prefix('settings')->name('settings.')->group(function () {
    Route::get('amenities', [AmenityController::class, 'index'])->name('amenities.index');
    Route::post('amenities', [AmenityController::class, 'store'])->name('amenities.store');
    Route::patch('amenities/{amenity}', [AmenityController::class, 'update'])->name('amenities.update');
    Route::delete('amenities/{amenity}', [AmenityController::class, 'destroy'])->name('amenities.destroy');

    Route::get('room-types', [RoomTypeController::class, 'index'])->name('room-types.index');
    Route::post('room-types', [RoomTypeController::class, 'store'])->name('room-types.store');
    Route::get('room-types/{roomType}', [RoomTypeController::class, 'show'])->name('room-types.show');
    Route::patch('room-types/{roomType}', [RoomTypeController::class, 'update'])->name('room-types.update');

    Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::post('rooms/range', [RoomController::class, 'storeRange'])->name('rooms.range');
    Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::patch('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
});
