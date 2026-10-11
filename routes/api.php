<?php

use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CheckOutController;
use App\Http\Controllers\OccupantsController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

Route::middleware(ResolveTenant::class)->name('attendance.')->group(function () {
    Route::post('/rooms/{room}/check-ins', CheckInController::class)->name('check-in');
    Route::post('/check-ins/{id}/check-out', CheckOutController::class)->name('check-out');
    Route::get('/rooms/{room}/occupants', OccupantsController::class)->name('occupants');
});
