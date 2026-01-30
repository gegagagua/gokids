<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/procredit-payment/success', function () {
    return view('procredit-payment-success');
});
Route::get('/procredit-payment/cancel', function () {
    return view('procredit-payment-cancel');
});

// API Documentation JSON endpoint for Swagger UI
Route::get('/api-docs.json', function () {
    return response()->file(storage_path('api-docs/api-docs.json'));
});

// Swagger UI route - handled by L5-Swagger package
