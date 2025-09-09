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
