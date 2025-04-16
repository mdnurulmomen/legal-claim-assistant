<?php

use App\Http\Controllers\Api\Buyer\BuyerController;
use Illuminate\Support\Facades\Route;


Route::prefix('buyer')->as('buyer.')
    ->controller(BuyerController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('list', 'buyerList')->name('buyer.list');
        $route->post('store-buyer', 'storeBuyer')->name('store.buyer');
        $route->get('show-buyer/{buyerId}', 'showBuyer')->name('show.buyer');
        $route->put('update-buyer/{buyerId}', 'updateBuyer')->name('update.buyer');
        $route->delete('delete-buyer/{buyerId}', 'deleteBuyer')->name('delete.buyer');
    });
