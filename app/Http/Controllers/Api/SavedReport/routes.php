<?php

use App\Http\Controllers\Api\SavedReport\SavedReportController;
use Illuminate\Support\Facades\Route;


Route::prefix('saved-report')->as('saved-report.')
    ->controller(SavedReportController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('list', 'reportList')->name('list');
        $route->post('store-report', 'storeReport')->name('store');
        $route->get('show-report/{reportUid}', 'showReport')->name('show');
        $route->put('update-report/{reportUid}', 'updateReport')->name('update');
        $route->delete('delete-report/{reportUid}', 'deleteReport')->name('delete');
    });
