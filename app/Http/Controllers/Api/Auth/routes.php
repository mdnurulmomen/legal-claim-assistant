<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\FirebaseAuthController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->as('auth.')
    ->group(function ($route) {

        $route->controller(AuthController::class)
            ->group(function($child) {
                $child->post('login', 'login')->name('login')->middleware(['guest', 'throttle:40,1']);
                $child->get('logout', 'logout')->name('logout')->middleware('auth:sanctum');
                $child->post('verify-token', 'verifyToken')->name('verify-token')->middleware('auth:sanctum');
            });

        $route->prefix('firebase')
            ->as('firebase')
            ->controller(FirebaseAuthController::class)
            ->group(function($child) {
                $child->post('check-login', 'checkLogin')->name('check.login')->middleware(['guest', 'throttle:40,1']);
                $child->post('login', 'login')->name('login')->middleware(['guest', 'throttle:40,1']);
            });
    });
