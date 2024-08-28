<?php

use App\Http\Controllers\Api\PlatformListPing\PlatformListPingController;
use Illuminate\Support\Facades\Route;


Route::prefix('/platform-lists/ping-logs/')->as('platformping.')
    ->controller(PlatformListPingController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('/fetch-data', 'fetchData')->name('get.fetch.data');
        $route->post('/fetch-data', 'fetchData')->name('post.fetch.data');
        $route->get('/get-filters', 'getFilterData')->name('get_filters');
    });
