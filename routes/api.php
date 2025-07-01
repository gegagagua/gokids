<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\GardenController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GardenGroupController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\ParentModelController;

Route::middleware([ForceJsonResponse::class])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::get('/gardens', [GardenController::class, 'index']);

    Route::get('/cities', [CityController::class, 'index']);

    Route::apiResource('garden-groups', GardenGroupController::class);
    Route::apiResource('cards', CardController::class);
    Route::apiResource('parents', ParentModelController::class);
});

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
