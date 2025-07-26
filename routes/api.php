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
use App\Http\Controllers\Api\GardenImageController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\CountryController;

Route::middleware([ForceJsonResponse::class])->group(function () {
    Route::get('/person-types', [PersonTypeController::class, 'index']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::apiResource('gardens', GardenController::class);
    Route::delete('/gardens/bulk-delete', [GardenController::class, 'bulkDestroy']);
    Route::apiResource('garden-images', GardenImageController::class);
    Route::apiResource('countries', CountryController::class);
    Route::apiResource('cities', CityController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::apiResource('garden-groups', GardenGroupController::class);
        Route::delete('/garden-groups/bulk-delete', [GardenGroupController::class, 'bulkDestroy']);
        Route::apiResource('countries', CountryController::class);
        Route::apiResource('cities', CityController::class);
        
        // Garden-filtered routes
        Route::middleware('garden.filter')->group(function () {
            Route::apiResource('cards', CardController::class);
            Route::delete('/cards/bulk-delete', [CardController::class, 'bulkDestroy']);
            Route::post('/cards/{id}/image', [CardController::class, 'uploadImage']);
            Route::apiResource('devices', DeviceController::class);
        });
        
        Route::apiResource('parents', ParentModelController::class);
        Route::apiResource('people', PeopleController::class);
    });
});

