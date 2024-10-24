<?php

use App\Http\Controllers\Api\PlatformList\PlatformIntegrationController;
use App\Http\Controllers\Api\PlatformList\PlatformListController;
use App\Http\Controllers\Api\PlatformList\PlatformSettingsController;
use App\Http\Controllers\Api\PlatformList\PlatformSpecsController;
use Illuminate\Support\Facades\Route;


Route::prefix('platform')->as('platform.')
    ->controller(PlatformListController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('list', 'platformList')->name('list');
        $route->get('get-source-list', 'getSourceList')->name('get-source-list');
        $route->get('buyer-list/{platformId}', 'buyerList')->name('buyer-list');
        $route->get('show/{platformId}', 'showPlatform')->name('show');
    });

Route::prefix('platform-integrations')->as('platform.integrations.')
    ->controller(PlatformIntegrationController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('number-formats', 'numberFormats')->name('number-formats');
        $route->get('buyer-types', 'buyerTypes')->name('buyer-types');
        $route->get('integration-methods', 'integrationMethods')->name('integration-methods');
        $route->get('cap-durations', 'capDurations')->name('cap-durations');
        $route->post('save-integration/{platformId}/{settingType}', 'saveIntegration')->name('save-integration');
        $route->post('save-full-integration/{platformId}', 'saveFullIntegration')->name('save-full-integration');
        $route->post('update-integration/{platformId}', 'updateIntegration')->name('update-integration');
        $route->post('store-integration/{platformId}', 'storeIntegration')->name('store-integration');
    });

Route::prefix('platform-specs')->as('platform.specs.')
    ->controller(PlatformSpecsController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('specs-list/{platformId}', 'specsList')->name('list');
        $route->delete('delete-specs/{specsId}', 'deleteSpecs')->name('delete.specs');
        $route->post('save-specs/{specsId}', 'saveSpecs')->name('save.specs');
        $route->post('store-specs', 'storeSpecs')->name('store.specs');
    });

Route::prefix('platform-settings')->as('platform.settings.')
    ->controller(PlatformSettingsController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->post('save-settings', 'saveSettings')->name('save.settings');
    });
