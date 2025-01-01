<?php

use App\Http\Controllers\Api\GlobalLog\GlobalLogController;
use Illuminate\Support\Facades\Route;


Route::prefix('global-log')
    ->as('global-log.')
    ->controller(GlobalLogController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('user-log/{type}', 'userLog')->name('user-logs');
    });
