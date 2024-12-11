<?php

use Illuminate\Support\Facades\Route;

Route::prefix('postback-logs')->as('postbacksLogs.')
    ->controller(\App\Http\Controllers\Api\PostbackLog\PostbackLogController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'index')->name('postbacklogs');
        $route->get('show/{id}', 'show')->name('show.globalPostback');
    });
