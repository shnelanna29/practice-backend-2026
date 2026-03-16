<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ReviewController;

// ==========================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (Доступны всем)
// ==========================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// ==========================================
// ЗАЩИЩЕННЫЕ МАРШРУТЫ (Требуется токен)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    // --- Аутентификация ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // --- Расписание (Чтение доступно всем авторизованным) ---
    Route::get('/schedule', [ScheduleController::class, 'index']);
    Route::get('/schedule/{id}', [ScheduleController::class, 'show']);
    
    // --- Расписание ресурса (день/неделя) и поиск свободных ---
    Route::get('/schedule/{id}/schedule', [ScheduleController::class, 'schedule']);
    Route::get('/schedule/available', [ScheduleController::class, 'available']);

    // --- Админские маршруты для Расписания ---
    Route::middleware('role:admin')->group(function () {
        Route::post('/admin/schedule', [ScheduleController::class, 'store']);
        Route::put('/admin/schedule/{id}', [ScheduleController::class, 'update']);
        Route::delete('/admin/schedule/{id}', [ScheduleController::class, 'destroy']);
    });

    // --- Бронирование (Клиенты и Админы) ---
    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);

    // --- Админские маршруты для Броней ---
    Route::get('/admin/bookings', [BookingController::class, 'index'])->middleware('role:admin');

    // --- Отзывы ---
    Route::get('/schedule/{scheduleItemId}/reviews', [ReviewController::class, 'index']);
    Route::post('/schedule/{scheduleItemId}/reviews', [ReviewController::class, 'store']);
});