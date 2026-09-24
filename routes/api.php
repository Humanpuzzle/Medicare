<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\DoctorAppointmentController;
use App\Http\Controllers\Api\DoctorAvailableSlotController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\PatientAppointmentController;
use App\Http\Controllers\Api\PatientController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::apiResource('doctors', DoctorController::class);
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('availabilities', AvailabilityController::class);

    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::post('appointments', [AppointmentController::class, 'store']);
    Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);

    Route::apiResource('doctors.appointments', DoctorAppointmentController::class)
        ->only(['index'])
        ->parameters(['doctors.appointments' => 'doctor']);

    Route::apiResource('patients.appointments', PatientAppointmentController::class)
        ->only(['index'])
        ->parameters(['patients.appointments' => 'patient']);

    Route::get('doctors/{doctor}/available-slots', [DoctorAvailableSlotController::class, 'index'])
        ->name('doctors.available-slots');
});
