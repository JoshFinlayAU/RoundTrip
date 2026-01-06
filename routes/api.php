<?php

use Illuminate\Support\Facades\Route;

Route::get('/targets', [\App\Http\Controllers\Api\TargetController::class, 'index']);
Route::get('/targets/{target}/series', [\App\Http\Controllers\Api\TargetController::class, 'series']);
