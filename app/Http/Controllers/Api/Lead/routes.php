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
        $route->post('update-leads', 'updateLeads')->name('update-leads');
        $route->get('lead-reports/{leadId}', 'getLeadReports')->name('lead.reports');
        $route->delete('delete-report/{reportId}', 'deleteReport')->name('delete.reports');
        $route->post('store-lead-reports', 'storeLeadReports')->name('store.lead-reports');
        $route->get('single-reports/{reportId}', 'getSingleReports')->name('single.reports');
        $route->put('update-lead-report/{reportId}', 'updateLeadReport')->name('update.lead-report');
        $route->get('buyer-integrations', 'getBuyerIntegrations')->name('buyer-integrations');
        $route->get('get-integrations', 'getIntegrations')->name('get-integrations');
        $route->get('get-lead-options/{type}', 'getLeadOptions')->name('get-lead-options');
    });
