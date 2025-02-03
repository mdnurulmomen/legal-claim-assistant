<?php

use App\Http\Controllers\Api\Test\TestController;
use Illuminate\Support\Facades\Route;


Route::prefix('test')
    ->as('test.')
    ->controller(TestController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->post('update-integration', 'updateIntegration')->name('update-integration');
        $route->post('update-data-items', 'updateDataItem')->name('update-data-item');
    });
