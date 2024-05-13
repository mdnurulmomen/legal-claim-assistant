<?php

use App\Http\Controllers\Api\PlatformList\PlatformListController;
use Illuminate\Support\Facades\Route;


Route::prefix('platform')->as('platform.')
    ->controller(PlatformListController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('list', 'platformList')->name('list');
    });
