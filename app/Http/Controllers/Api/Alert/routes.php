<?php

use Illuminate\Support\Facades\Route;

Route::prefix('alert')->as('alert.')
    ->controller(\App\Http\Controllers\Api\Alert\SettingsController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {

        // load settings
        $route->get('settings', 'settings')->name('settings');

        // update settings
        $route->put('settings', 'updateSettings')->name('update-settings');

        // load rules
        $route->get('rules', 'rules')->name('rules');

        // update rules
        $route->put('rules', 'updateRules')->name('update-rules');
    });

// LogsController
Route::prefix('alert')->as('alert.')
    ->controller(\App\Http\Controllers\Api\Alert\LogsController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        // load logs
        $route->get('logs', 'retrieveLogs')->name('logs');
    });
