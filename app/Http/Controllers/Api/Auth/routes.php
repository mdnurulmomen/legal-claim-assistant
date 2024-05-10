<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->as('auth.')
    ->controller(AuthController::class)
    ->group(function ($route) {
        $route->post('login', 'login')->name('login')->middleware(['guest', 'throttle:40,1']);
        $route->get('logout', 'logout')->name('logout')->middleware('auth:sanctum');
        $route->post('verify-token', 'verifyToken')->name('verify-token')->middleware('auth:sanctum');
    });
