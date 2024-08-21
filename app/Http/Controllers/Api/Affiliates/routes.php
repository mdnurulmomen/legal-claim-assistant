<?php

use Illuminate\Support\Facades\Route;

Route::prefix('affiliates')->as('affiliate.')
    ->controller(\App\Http\Controllers\Api\Affiliates\AffiliateController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'affiliates')->name('index');
        $route->get('show/{id}', 'affiliate')->name('show');
        $route->put('update/{id}', 'update')->name('update');
        $route->post('create', 'create')->name('store');
        $route->put('affiliate-status/{id}', 'toggleStatus')->name('toggle-status');

        $route->post('impersonate/{id}', 'impersonate')->name('impersonate');
    });
