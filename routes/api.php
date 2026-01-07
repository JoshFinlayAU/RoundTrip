<?php

use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/auth/user', [\App\Http\Controllers\Api\AuthController::class, 'user']);
    Route::put('/auth/password', [\App\Http\Controllers\Api\AuthController::class, 'updatePassword']);
    Route::put('/auth/profile', [\App\Http\Controllers\Api\AuthController::class, 'updateProfile']);

    // Groups
    Route::get('/groups', [\App\Http\Controllers\Api\GroupController::class, 'index']);
    Route::post('/groups', [\App\Http\Controllers\Api\GroupController::class, 'store']);
    Route::get('/groups/{group}', [\App\Http\Controllers\Api\GroupController::class, 'show']);
    Route::put('/groups/{group}', [\App\Http\Controllers\Api\GroupController::class, 'update']);
    Route::delete('/groups/{group}', [\App\Http\Controllers\Api\GroupController::class, 'destroy']);

    // Targets
    Route::get('/targets', [\App\Http\Controllers\Api\TargetController::class, 'index']);
    Route::post('/targets', [\App\Http\Controllers\Api\TargetController::class, 'store']);
    Route::get('/targets/{target}', [\App\Http\Controllers\Api\TargetController::class, 'show']);
    Route::put('/targets/{target}', [\App\Http\Controllers\Api\TargetController::class, 'update']);
    Route::delete('/targets/{target}', [\App\Http\Controllers\Api\TargetController::class, 'destroy']);
    Route::get('/targets/{target}/series', [\App\Http\Controllers\Api\TargetController::class, 'series']);
});
