<?php

use App\Http\Controllers\Api\SiteSetting\SiteSettingController;
use Illuminate\Support\Facades\Route;


Route::prefix('setting')->as('setting.')
    ->controller(SiteSettingController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('site-settings/{slug}', 'getSiteSettings')->name('get-site-settings');
        $route->post('save-site-settings', 'saveSiteSettings')->name('save-site-settings');
    });
