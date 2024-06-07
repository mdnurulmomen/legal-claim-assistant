<?php

use App\Http\Controllers\Api\Reporting\ReportingController;
use Illuminate\Support\Facades\Route;


Route::prefix('report')->as('report.')
    ->controller(ReportingController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('list', 'reportingList')->name('list');
        $route->get('report-tabs', 'getReportingTabs')->name('get.reporting.tabs');
        $route->get('filter-dropdown-values', 'getFilterDropdownValues')->name('get.filter.dropdown.values');
        $route->get('performance-data', 'getPerformanceData')->name('get.performance.data');
    });
