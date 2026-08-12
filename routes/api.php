<?php

use App\Http\Controllers\AIChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\TeleconsultationController;
use App\Http\Controllers\Auth\UpdatePasswordController;
use App\Http\Controllers\UserController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->put('/user/password', [UpdatePasswordController::class, 'update']);

Route::middleware(['auth:sanctum'])->delete('/user', [UserController::class, 'destroy']);

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware(['guest', 'web'])
    ->name('register');

Route::get('/doctors', [DoctorController::class, 'index']);
Route::post('/doctors', [DoctorController::class, 'store']);
Route::get('/doctors/{doctor}', [DoctorController::class, 'show']);
Route::put('/doctors/{doctor}', [DoctorController::class, 'update']);
Route::delete('/doctors/{doctor}', [DoctorController::class, 'destroy']);

Route::get('/appointments', [AppointmentController::class, 'index']);
Route::post('/appointments', [AppointmentController::class, 'store']);
Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);

Route::get('/teleconsultations', [TeleconsultationController::class, 'index']);
Route::get('/user/teleconsultations', [TeleconsultationController::class, 'getUserTeleconsultations'])->middleware('auth:sanctum');
Route::post('/teleconsultations', [TeleconsultationController::class, 'store']);
Route::get('/teleconsultations/{teleconsultation}', [TeleconsultationController::class, 'show']);
Route::put('/teleconsultations/{teleconsultation}', [TeleconsultationController::class, 'update']);
Route::delete('/teleconsultations/{teleconsultation}', [TeleconsultationController::class, 'destroy']);
Route::patch('/teleconsultations/{teleconsultation}/status', [TeleconsultationController::class, 'updateStatus']);

Route::post('/ask-ai', [AIChatController::class, 'ask']);

