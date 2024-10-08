<?php

use Illuminate\Support\Facades\Route;

Route::prefix('settings/global-postback')->as('globalPostbacks.')
    ->controller(\App\Http\Controllers\Api\GlobalPostback\GlobalPostbackController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'globalPostbacks')->name('globalPostbacks');
        $route->get('show/{id}', 'globalPostback')->name('show.globalPostback');
        $route->put('update/{id}', 'update')->name('update');
        $route->post('create', 'create')->name('store');
        $route->delete('delete/{id}', 'delete')->name('delete'); 
    });
