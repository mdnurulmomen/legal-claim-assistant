<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('Hello World!', 200, [
        'Content-Type' => 'text/plain',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'deny',
        'X-XSS-Protection' => '1; mode=block'
    ]);
});

Route::get('/test', function (Request $request) {
    return response('Hello World!', 200);
});
