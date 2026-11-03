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
    ->middleware(['guest', 'throttle:10,1'])
    ->name('api.login');

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('api.register');

Route::get('/doctors', [DoctorController::class, 'index']);
Route::middleware(['auth:sanctum', 'role:admin'])->post('/doctors', [DoctorController::class, 'store']);
Route::get('/doctors/{doctor}', [DoctorController::class, 'show']);
Route::middleware(['auth:sanctum', 'role:admin'])->put('/doctors/{doctor}', [DoctorController::class, 'update']);
Route::middleware(['auth:sanctum', 'role:admin'])->delete('/doctors/{doctor}', [DoctorController::class, 'destroy']);

Route::middleware('auth:sanctum')->get('/appointments', [AppointmentController::class, 'index']);
Route::middleware('auth:sanctum')->post('/appointments', [AppointmentController::class, 'store']);
Route::middleware('auth:sanctum')->get('/appointments/{appointment}', [AppointmentController::class, 'show']);
Route::middleware('auth:sanctum')->put('/appointments/{appointment}', [AppointmentController::class, 'update']);
Route::middleware('auth:sanctum')->delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
Route::middleware('auth:sanctum')->patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);

Route::middleware('auth:sanctum')->get('/teleconsultations', [TeleconsultationController::class, 'index']);
Route::get('/user/teleconsultations', [TeleconsultationController::class, 'getUserTeleconsultations'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->post('/teleconsultations', [TeleconsultationController::class, 'store']);
Route::middleware('auth:sanctum')->get('/teleconsultations/{teleconsultation}', [TeleconsultationController::class, 'show']);
Route::middleware('auth:sanctum')->put('/teleconsultations/{teleconsultation}', [TeleconsultationController::class, 'update']);
Route::middleware('auth:sanctum')->delete('/teleconsultations/{teleconsultation}', [TeleconsultationController::class, 'destroy']);
Route::middleware('auth:sanctum')->patch('/teleconsultations/{teleconsultation}/status', [TeleconsultationController::class, 'updateStatus']);

Route::middleware(['auth:sanctum', 'throttle:10,1'])->post('/ask-ai', [AIChatController::class, 'ask']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:sanctum');
Route::put('/user/profile', [UserController::class, 'update'])->middleware('auth:sanctum');
Route::post('/contact', [\App\Http\Controllers\ContactController::class, 'store'])->middleware('throttle:5,1');
Route::get('/contact', [\App\Http\Controllers\ContactController::class, 'index'])->middleware(['auth:sanctum', 'role:admin']);

Route::middleware('auth:sanctum')->prefix('teleconsultations/{teleconsultation}')->group(function () {
    $controller = \App\Http\Controllers\ConsultationCommunicationController::class;
    Route::get('/messages', [$controller, 'messages'])->middleware('throttle:120,1');
    Route::post('/messages', [$controller, 'send'])->middleware('throttle:30,1');
    Route::get('/video', [$controller, 'video']);
    Route::get('/signals', [$controller, 'signals'])->middleware('throttle:120,1');
    Route::post('/signals', [$controller, 'signal'])->middleware('throttle:180,1');
});

// Profile photos are public, like doctor directory photos. UUID paths prevent name guessing.
Route::get('/profile-photos/{name}', function (string $name) {
 abort_unless(preg_match('/^[a-f0-9-]{36}\.(jpg|jpeg|png|webp)$/', $name), 404);
 $disk = \Illuminate\Support\Facades\Storage::disk('local');
 abort_unless($disk->exists('profile-photos/'.$name), 404);
 return response()->file($disk->path('profile-photos/'.$name), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=86400']);
});

Route::get('/specialities', fn () => response()->json(config('specialities')));

Route::get('/shop/products', [\App\Http\Controllers\ShopController::class,'products']);
Route::middleware(['auth:sanctum','role:admin'])->group(function() {
 Route::post('/shop/products', [\App\Http\Controllers\ShopController::class,'saveProduct']);
 Route::put('/shop/products/{product}', [\App\Http\Controllers\ShopController::class,'saveProduct']);
});
Route::middleware(['auth:sanctum','role:user,admin'])->group(function() {
 Route::get('/shop/orders', [\App\Http\Controllers\ShopController::class,'orders']);
 Route::patch('/shop/orders/{order}/status', [\App\Http\Controllers\ShopController::class,'status']);
});
Route::post('/shop/orders', [\App\Http\Controllers\ShopController::class,'createOrder'])->middleware(['auth:sanctum','role:user','throttle:10,1']);

Route::post('/shop/quote', [\App\Http\Controllers\ShopController::class,'quote'])->middleware(['auth:sanctum','role:user']);

Route::get('/product-images/{name}', function(string $name) {
 abort_unless(preg_match('/^[a-f0-9-]{36}\.(jpg|jpeg|png|webp)$/',$name),404);
 $disk=\Illuminate\Support\Facades\Storage::disk('local');abort_unless($disk->exists('product-images/'.$name),404);
 return response()->file($disk->path('product-images/'.$name),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'public, max-age=86400']);
});
