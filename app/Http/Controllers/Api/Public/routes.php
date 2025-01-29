<?php

use App\Http\Controllers\Api\Public\PublicController;
use App\Http\Middleware\PlatformTokenVerifier;
use Illuminate\Support\Facades\Route;


Route::prefix('public')->as('public.')
    ->controller(PublicController::class)
    ->middleware([PlatformTokenVerifier::class])
    ->group(function ($route) {
        $route->post('lead-details', 'leadDetails')->name('lead-details');
        $route->post('retainer-event/{affLeadId}', 'retainerEvent')->name('retainer-event');
    });
