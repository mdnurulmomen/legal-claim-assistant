<?php

use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('dashboard')->as('dashboard.')
    ->controller(DashboardController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('dashboard-total-stats', 'totalStats')->name('dashboard.totalstats');
        $route->get('performance-stats', 'getPerformanceStats')->name('dashboard.performancestats');
        $route->get('performance-graph', 'getGraph')->name('dashboard.performancegraph');
        $route->get('top-affiliates', 'getTopAffiliates')->name('dashboard.topaffiliates');
        $route->get('top-buyers', 'getTopBuyers')->name('dashboard.topbuyers');
        $route->get('top-lists', 'getTopLists')->name('dashboard.toplists');

    });
