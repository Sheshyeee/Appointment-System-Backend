<?php

use App\Http\Controllers\AdminDashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DentistController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:60,1')->group(function () {
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login']);
  Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
  Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
});

// Public booking flow — no account required.
// Throttle harder here since it's unauthenticated and open to abuse.
Route::middleware('throttle:60,1')->group(function () {
  Route::get('/dentists', [DentistController::class, 'index']);
  Route::get('/services', [ServiceController::class, 'index']);
  Route::get('/services/{id}', [ServiceController::class, 'show']);

  Route::get('/appointments/available-slots', [AppointmentController::class, 'availableSlots']);
  Route::get('/appointments/booked-summary', [AppointmentController::class, 'bookedSummary']);
});

// Guest appointment creation — throttled tighter still, this is a write.
Route::middleware('throttle:10,1')->group(function () {
  Route::post('/appointments', [AppointmentController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Protected routes (require valid Sanctum token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
  Route::post('/logout', [AuthController::class, 'logout']);
  Route::get('/me', [AuthController::class, 'me']);

  Route::get('/appointments', [AppointmentController::class, 'index']);

  Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])
    ->middleware('can:view,appointment');
  Route::put('/appointments/{appointment}/update', [AppointmentController::class, 'update'])
    ->middleware('can:update,appointment');
  Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])
    ->middleware('can:delete,appointment');

  Route::get('/profile', [ProfileController::class, 'show']);
  Route::put('/profile', [ProfileController::class, 'update']);

  Route::get('/notifications', [NotificationController::class, 'index']);
  Route::put('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
  Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);

  Route::middleware('role:staff,admin')->group(function () {
    Route::get('/dashboard/dentist', function () {
      return response()->json(['message' => 'Dentist dashboard data']);
    });

    Route::get('/staff', [DentistController::class, 'staff']);
    Route::get('/patients', [PatientController::class, 'index']);
    Route::get('/patients/{id}', [PatientController::class, 'show']);
    Route::post('/patients/{id}/notes', [PatientController::class, 'addNote']);

    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'updateInfo']);
    Route::put('/settings/hours', [SettingsController::class, 'updateHours']);
  });

  Route::middleware('role:admin')->group(function () {
    Route::post('/dentists', [DentistController::class, 'store']);
    Route::put('/dentists/{id}/update', [DentistController::class, 'updateDentist']);
    Route::get('/dashboard/admin', [AdminDashboardController::class, 'overview']);
    Route::delete('/dentists/{id}', [DentistController::class, 'destroy']);

    Route::put('/staff/{id}/update', [DentistController::class, 'updateStaff']);
    Route::delete('/staff/{id}', [DentistController::class, 'destroyStaff']);
    Route::post('/register-user', [DentistController::class, 'registerUser']);

    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{id}/update', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

    Route::post('/patients', [PatientController::class, 'store']);
    Route::put('/patients/{id}/update', [PatientController::class, 'update']);
    Route::delete('/patients/{id}', [PatientController::class, 'destroy']);
  });
});
