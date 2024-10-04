<?php

use Illuminate\Support\Facades\Route;

Route::prefix('affiliate-log')->as('affiliate.log')
    ->controller(\App\Http\Controllers\Api\AffiliateLog\AffiliateLogController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'affiliateLogs')->name('index');
        $route->get('affiliate-user', 'affiliateUser')->name('affiliate.user');
    });
