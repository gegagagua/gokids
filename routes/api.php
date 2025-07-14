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
use App\Http\Controllers\Api\PersonTypeController;
use App\Http\Controllers\Api\PeopleController;

Route::middleware([ForceJsonResponse::class])->group(function () {
    Route::get('/cities', [CityController::class, 'index']);
    Route::get('/person-types', [PersonTypeController::class, 'index']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::apiResource('gardens', GardenController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::apiResource('garden-groups', GardenGroupController::class);
        Route::apiResource('cards', CardController::class);
        Route::apiResource('parents', ParentModelController::class);
        Route::apiResource('people', PeopleController::class);
    });
});

