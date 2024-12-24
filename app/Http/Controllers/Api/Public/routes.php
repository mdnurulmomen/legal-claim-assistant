<?php

use App\Http\Controllers\Api\Public\PublicController;
use Illuminate\Support\Facades\Route;


Route::prefix('public')->as('public.')
    ->controller(PublicController::class)
    ->group(function ($route) {
        $route->post('lead-details', 'leadDetails')->name('lead-details');
    });
