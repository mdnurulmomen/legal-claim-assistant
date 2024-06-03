<?php

use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('dashboard')->as('dashboard.')
    ->controller(DashboardController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('dashboard-total-stats', 'totalStats')->name('dashboard.totalstats');
    });
