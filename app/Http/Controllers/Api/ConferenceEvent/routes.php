<?php

use App\Http\Controllers\Api\ConferenceEvent\ConferenceEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('conference')->as('conference.')
    ->controller(ConferenceEventController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'index')->name('index');
        $route->post('create', 'create')->name('store');
        $route->get('show/{id}', 'show')->name('show');
        $route->post('update/{id}', 'update')->name('update');
        $route->delete('delete/{id}', 'delete')->name('delete');
        // $route->post('testUpload', 'postUpload')->name('postUpload');
    });
