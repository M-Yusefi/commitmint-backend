<?php

//use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\CheckinController;


// Piblic Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group( function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/store', [GoalController::class,'store']);
    Route::get('/index', [GoalController::class, 'index']);

    Route::post('/goal_store', [GoalController::class, 'store']);
    Route::get('/goal_index', [GoalController::class, 'index']);
});

