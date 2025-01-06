<?php

use App\Http\Controllers\Api\Excel\ExcelController;
use App\Http\Controllers\Api\Lead\LeadController;
use App\Http\Controllers\Api\Reporting\ReportingController;
use App\Http\Middleware\TokenValidation;
use Illuminate\Support\Facades\Route;


Route::prefix('excels')
    ->as('excels.')
    ->group(function ($route) {

        $route->prefix('export')
            ->as('export.')
            ->middleware([TokenValidation::class])
            ->group(function ($route) {
                $route->get('/leads', [LeadController::class, 'list'])->name('leads');
                $route->get('/report', [ReportingController::class, 'reportingList'])->name('report-list');
            });

        $route->prefix('import')
            ->middleware('auth:sanctum')
            ->as('import.')->group(function ($route){
                $route->post('/upload-lead-csv', [ExcelController::class, 'uploadLeadCsv'])->name('upload-lead-csv');
            });
    });
