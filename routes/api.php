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
use App\Http\Controllers\Api\DisterController;
use App\Http\Controllers\Api\SmsGatewayController;
use App\Http\Controllers\Api\PaymentGatewayController;

Route::middleware([ForceJsonResponse::class])->group(function () {
    Route::get('/person-types', [PersonTypeController::class, 'index']);
    Route::post('/person-types', [PersonTypeController::class, 'store']);
    Route::delete('/person-types/{id}', [PersonTypeController::class, 'destroy']);
    
    // SMS Gateway routes (public)
    Route::get('/sms-gateways', [SmsGatewayController::class, 'index']);
    
    // Payment Gateway routes (public)
    Route::get('/payment-gateways', [PaymentGatewayController::class, 'index']);
    
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    // Dister creation (no authentication required)
    Route::post('/disters', [DisterController::class, 'store']);
    
    // Gardens: require auth to enforce per-dister filtering
    Route::apiResource('garden-images', GardenImageController::class);
    Route::apiResource('countries', CountryController::class);
    Route::apiResource('cities', CityController::class);
    Route::apiResource('people', PeopleController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        // Place specific routes before resource to avoid {garden} binding capturing 'export'
        Route::get('/gardens/export', [GardenController::class, 'export']);
        Route::delete('/gardens/bulk-delete', [GardenController::class, 'bulkDestroy']);
        Route::patch('/gardens/{id}/status', [GardenController::class, 'updateStatus']);
        Route::patch('/gardens/{id}/dister', [GardenController::class, 'updateDister']);
        Route::apiResource('gardens', GardenController::class);
        
        // Dister routes (authenticated)
        Route::apiResource('disters', DisterController::class);
        Route::patch('/disters/{id}/change-password', [DisterController::class, 'changePassword']);
        Route::post('/disters/logout', [DisterController::class, 'logout']);
        Route::get('/disters/profile', [DisterController::class, 'profile']);
        
        Route::apiResource('devices', DeviceController::class);
        Route::patch('/devices/{id}/status', [DeviceController::class, 'updateStatus']);
        Route::post('/devices/{id}/regenerate-code', [DeviceController::class, 'regenerateCode']);

        Route::apiResource('garden-groups', GardenGroupController::class);
        Route::delete('/garden-groups/bulk-delete', [GardenGroupController::class, 'bulkDestroy']);
        
        // Garden-filtered routes
        Route::middleware(['garden.filter', ForceJsonResponse::class])->group(function () {
            // Specific routes before resource to avoid binding conflicts
            Route::get('/cards/export', [CardController::class, 'export']);
            Route::get('/cards', [CardController::class, 'index']);
            
            Route::post('/cards', [CardController::class, 'store']);
            Route::get('/cards/{id}', [CardController::class, 'show']);
            Route::put('/cards/{id}', [CardController::class, 'update']);
            Route::patch('/cards/{id}', [CardController::class, 'update']);
            Route::delete('/cards/bulk-delete', [CardController::class, 'bulkDestroy']);
            Route::post('/cards/move-to-group', [CardController::class, 'moveToGroup']);
            // New endpoints for updating only parent_verification and license
            Route::patch('/cards/{id}/parent-verification', [CardController::class, 'updateParentVerification']);
            Route::patch('/cards/{id}/license', [CardController::class, 'updateLicense']);
            Route::patch('/cards/{id}/status', [CardController::class, 'updateStatus']);
            Route::patch('/cards/{id}/change-main-garden-image', [CardController::class, 'changeMainGardenImage']);
            
            Route::post('/cards/{id}/regenerate-code', [CardController::class, 'regenerateCode']);
            Route::post('/cards/{id}/restore', [CardController::class, 'restore']);
        });
        
        // Public card delete route (no authentication required)
        Route::delete('/cards/{id}', [CardController::class, 'destroy']);
        Route::get('/cards/spam', [CardController::class, 'getSpamCards']);
        Route::apiResource('parents', ParentModelController::class);
       
    });

    // Card login routes (no authentication required)
    Route::post('/cards/login', [CardController::class, 'login']);

    Route::post('/cards/send-otp', [CardController::class, 'sendOtp']);
    Route::post('/cards/verify-otp', [CardController::class, 'verifyOtp']);
    Route::post('/cards/{id}/image', [CardController::class, 'uploadImage']);
    Route::patch('/cards/{id}/delete-as-spam', [CardController::class, 'deleteAsSpam']);
    
    // Card authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/cards/me', [CardController::class, 'me']);
    });
    
    // Device login route (no authentication required)
    Route::post('/devices/login', [DeviceController::class, 'deviceLogin']);
});

