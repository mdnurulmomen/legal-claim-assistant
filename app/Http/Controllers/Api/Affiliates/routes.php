<?php

use App\Http\Controllers\Api\Affiliates\AffiliateController;
use Illuminate\Support\Facades\Route;

Route::prefix('affiliates')->as('affiliate.')
    ->controller(AffiliateController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'affiliates')->name('index');
        $route->get('show/{id}', 'affiliate')->name('show');
        $route->put('update/{id}', 'update')->name('update');
        $route->post('create', 'create')->name('store');
        $route->put('affiliate-status/{id}', 'toggleStatus')->name('toggle-status');
        $route->get('affiliate-list',  'affiliateList')->name('affiliate-list');
        $route->post('save-report-columns/{userId}', 'saveReportColumns')->name('save-report-columns');
        $route->post('impersonate/{id}', 'impersonate')->name('impersonate');
    });
