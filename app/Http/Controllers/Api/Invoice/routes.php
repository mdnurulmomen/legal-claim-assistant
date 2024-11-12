<?php

use App\Http\Controllers\Api\Invoice\InvoiceController;
use Illuminate\Support\Facades\Route;


Route::prefix('finance')->as('platform.')
    ->controller(InvoiceController::class)
   ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('invoices', 'invoiceList')->name('invoice-list');
        $route->get('invoice/{tag}', 'invoiceByTag')->name('invoice');
        $route->post('invoice/{tag}/update', 'invoiceUpdate')->name('invoice-update');
        $route->get('invoice-status', 'invoiceStatus')->name('invoice-status');
        $route->put('update-invoice-status/{invoiceId}', 'updateInvoiceStatus')->name('update-invoice-status');
    });
