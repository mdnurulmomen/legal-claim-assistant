<?php

use App\Http\Controllers\Api\Lead\LeadController;
use App\Http\Controllers\Api\Public\PublicController;
use App\Http\Middleware\PlatformTokenVerifier;
use App\Http\Middleware\UserApiTokenVerifier;
use Illuminate\Support\Facades\Route;


Route::prefix('public')->as('public.')
    ->group(function ($route) {
        $route->controller(PublicController::class)
            ->middleware([PlatformTokenVerifier::class])
            ->group(function ($childRoute) {
                $childRoute->post('lead-details', 'leadDetails')->name('lead-details');
                $childRoute->post('retainer-event/{affLeadId}', 'retainerEvent')->name('retainer-event');
            });

        $route->middleware([UserApiTokenVerifier::class])
            ->group(function ($childRoute) {
                $childRoute->post('lead-list', [LeadController::class, 'list'])->name('lead-list');
                $childRoute->get('get-integrations', [LeadController::class, 'getIntegrations'])->name('get-integration');
                $childRoute->get('filter-options/{type}', [LeadController::class, 'getLeadOptions'])->name('filter-options');
                $childRoute->get('rules-and-default-columns', [PublicController::class, 'rulesAndDefaultColumns'])->name('rules-and-default-columns');
            });
    });
