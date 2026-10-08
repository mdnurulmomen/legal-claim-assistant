<?php

use App\Http\Controllers\Api\GeneralInformation\GeneralInformationController;
use Illuminate\Support\Facades\Route;

Route::prefix('general-information')->as('GeneralInformation.')
    ->controller(GeneralInformationController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'showFirstOne')->name('index');
        // $route->post('create', 'create')->name('store');
        // $route->get('show/{tag}', 'show')->name('show');
        $route->post('update/{tag}', 'update')->name('update');
        // $route->delete('delete/{tag}', 'delete')->name('delete');
    });
