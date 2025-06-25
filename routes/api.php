<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/test-array', function () {
    return [
        'status'  => 'success',
        'message' => 'This is a test response',
        'data'    => [
            'foo' => 'bar',
            'baz' => 'qux',
        ],
    ];
});