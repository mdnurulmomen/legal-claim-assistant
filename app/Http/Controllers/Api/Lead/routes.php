<?php

use App\Http\Controllers\Api\Lead\LeadController;
use Illuminate\Support\Facades\Route;


Route::prefix('leads')->as('leads.')
    ->controller(LeadController::class)
   ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('list', 'list')->name('list');
        $route->get('headers', 'getLeadHeaders')->name('headers');
        $route->get('lead-info/{leadId}', 'getLeadInfo')->name('lead-info');
    });
