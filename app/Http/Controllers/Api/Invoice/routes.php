<?php

use App\Http\Controllers\Api\Invoice\InvoiceController;
use Illuminate\Support\Facades\Route;


Route::prefix('finance')->as('platform.')
    ->controller(InvoiceController::class)
//    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('invoices', 'invoiceList')->name('invoice-list');
        $route->get('invoice/{tag}', 'invoiceByTag')->name('invoice');
    });
