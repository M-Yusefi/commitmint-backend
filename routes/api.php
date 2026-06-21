<?php

//use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\CheckinController;


// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/quote', [AuthController::class, 'quote']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('user')->group(function () {
        Route::get('/', [AuthController::class, 'user']);
        Route::post('/update', [AuthController::class, 'update']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::delete('/delete', [AuthController::class, 'delete']);
    });

    Route::prefix('goal')->group(function () {
        Route::post('/store', [GoalController::class, 'store']);
        Route::get('/index', [GoalController::class, 'index']);
    });

    Route::prefix('checkin')->group(function () {
        Route::post('/store/{goal}', [CheckinController::class, 'store']);
        Route::get('/{goal}', [CheckinController::class, 'index']);
        Route::get('/{goal}/checkin/{checkin}', [CheckinController::class, 'show']);
        Route::get('/{goal}/stats', [CheckinController::class, 'stats']);
    });
});