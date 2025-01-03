<?php

use App\Http\Controllers\Api\CreativeTemplate\CreativeTemplateController;
use App\Http\Controllers\Api\CreativeTemplate\TemplateOfferController;
use Illuminate\Support\Facades\Route;

Route::prefix('creative-template')->as('creative-template.')
    ->controller(CreativeTemplateController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'index')->name('index');
        $route->get('show/{id}', 'show')->name('show');
        $route->post('create', 'store')->name('store');
        $route->post('update/{id}', 'update')->name('update');
        $route->delete('delete/{id}', 'delete')->name('delete');
    });

Route::prefix('template-offer')->as('template-offer.')
    ->controller(TemplateOfferController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'index')->name('index');
        $route->get('offers', 'allOfferSelectFormat')->name('allOfferSelectFormat');
        // $route->get('show/{id}', 'show')->name('show');
        $route->post('create', 'store')->name('store');
        $route->post('update/{id}', 'update')->name('update');
        $route->delete('delete/{id}', 'delete')->name('delete');
    });
