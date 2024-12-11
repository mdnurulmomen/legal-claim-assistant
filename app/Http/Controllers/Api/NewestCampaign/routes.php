<?php

use App\Http\Controllers\Api\NewestCampaign\NewestCampaignController;
use Illuminate\Support\Facades\Route;

Route::prefix('newest-campaign')->as('newestcampaign.')
    ->controller(NewestCampaignController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'index')->name('index');
        $route->post('create', 'create')->name('store');
        $route->get('show/{tag}', 'show')->name('show');
        $route->post('update/{tag}', 'update')->name('update');
        $route->delete('delete/{tag}', 'delete')->name('delete');
    });
