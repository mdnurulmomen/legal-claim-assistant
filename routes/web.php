<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('Hello World!', 200, [
        'Content-Type' => 'text/plain',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'deny',
        'X-XSS-Protection' => '1; mode=block'
    ]);
});

Route::get('/test', function () {
    return response('Hello World!', 200);
    // $users = Cache::remember('active_users', 60, function () {
    //     return User::query()->get();
    // });

    // return $users;
});
