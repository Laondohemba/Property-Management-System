<?php

use App\Modules\Inventory\Http\Controllers\AmenityController;
use App\Modules\Inventory\Http\Controllers\InventoryBlockController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\RoomController;
use App\Modules\Inventory\Http\Controllers\RoomTypeController;
use App\Modules\Inventory\Http\Controllers\RoomTypeImageController;
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

    // Gallery: one image per upload request, so the SPA can show progress per file.
    Route::post('room-types/{roomType}/images', [RoomTypeImageController::class, 'store'])
        ->middleware('throttle:60,1')->name('room-types.images.store');
    Route::put('room-types/{roomType}/images/order', [RoomTypeImageController::class, 'reorder'])->name('room-types.images.order');
    Route::patch('room-types/{roomType}/images/{image}', [RoomTypeImageController::class, 'update'])
        ->whereUuid('image')->name('room-types.images.update');
    Route::delete('room-types/{roomType}/images/{image}', [RoomTypeImageController::class, 'destroy'])
        ->whereUuid('image')->name('room-types.images.destroy');

    Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::post('rooms/range', [RoomController::class, 'storeRange'])->name('rooms.range');
    Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::patch('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
});

/*
| Master inventory (CHN-01) and blocks (CAL-06): allotments per room type and night.
*/

Route::middleware(['auth:sanctum', 'property'])->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('index');
    Route::put('overbooking', [InventoryController::class, 'overbooking'])->name('overbooking');

    Route::get('blocks', [InventoryBlockController::class, 'index'])->name('blocks.index');
    Route::post('blocks', [InventoryBlockController::class, 'store'])->middleware('idempotent')->name('blocks.store');
    Route::post('blocks/{block}/release', [InventoryBlockController::class, 'release'])->name('blocks.release');
});
