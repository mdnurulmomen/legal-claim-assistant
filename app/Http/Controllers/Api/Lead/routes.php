<?php

use App\Http\Controllers\Api\Lead\LeadController;
use App\Http\Controllers\Api\Lead\LeadControllerV2;
use Illuminate\Support\Facades\Route;

Route::prefix('leads')->as('leads.')
    ->controller(LeadController::class)
   ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->post('list', 'list')->name('list');
        $route->get('list', 'list')->name('list.get');
        $route->get('get-latest-leads', 'getLatestLeads')->name('get-latest-leads');
        $route->get('headers', 'getLeadHeaders')->name('headers');
        $route->get('lead-info/{leadId}', 'getLeadInfo')->name('lead-info');
        $route->post('update-leads', 'updateLeads')->name('update-leads');
        $route->get('lead-reports/{leadId}', 'getLeadReports')->name('lead.reports');
        $route->delete('delete-report/{reportId}', 'deleteReport')->name('delete.reports');
        $route->post('store-lead-reports', 'storeLeadReports')->name('store.lead-reports');
        $route->get('single-reports/{reportId}', 'getSingleReports')->name('single.reports');
        $route->put('update-lead-report/{reportId}', 'updateLeadReport')->name('update.lead-report');
        $route->get('buyer-integrations', 'getBuyerIntegrations')->name('buyer-integrations');
        $route->get('get-integrations', 'getIntegrations')->name('get-integrations');
        $route->get('get-lead-options/{type}', 'getLeadOptions')->name('get-lead-options');
        $route->put('update-filled-fields', 'updateFilledFields')->name('update.filled-fields');
        $route->get('lead-log-info/{id}',  'getLeadLogInfo')->name('lead-log-info');
        $route->post('filtered-leads', 'filteredLeads')->name('filtered-leads');
    });

Route::prefix('leads-v2')->as('leads-v2.')
    ->controller(LeadControllerV2::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->put('bulk-update-leads', 'bulkUpdateLeads')->name('bulk-update-leads');
    });
