<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/bog-payment/success', function () {
    return view('bog-payment-success');
});
Route::get('/bog-payment/cancel', function () {
    return view('bog-payment-cancel');
});

// API Documentation JSON endpoint for Swagger UI
Route::get('/api-docs.json', function () {
    return response()->file(storage_path('api-docs/api-docs.json'));
});
