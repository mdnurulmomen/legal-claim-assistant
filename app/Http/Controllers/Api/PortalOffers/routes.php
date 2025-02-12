<?php

use App\Http\Controllers\Api\PortalOffers\PortalOffersController;
use App\Http\Controllers\Api\PortalOffers\OfferRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal-offers')->as('portal-offers.')
    ->controller(PortalOffersController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'index')->name('index');
        $route->get('show/{tag}', 'show')->name('show');
        $route->post('create', 'store')->name('store');
        $route->post('update/{tag}', 'update')->name('update');
        $route->delete('delete/{tag}', 'delete')->name('delete');
        $route->get('lists', 'lists')->name('lists');
        $route->get('criteria/{tag}', 'criteria')->name('criteria');

        // Offer Request routes
        $route->prefix('requests')->as('requests.')
            ->controller(OfferRequestController::class)
            ->group(function ($route) {
                $route->get('/', 'index')->name('index');
                $route->post('{id}/approve', 'approve')->name('approve');
                $route->post('{id}/disapprove', 'disapprove')->name('disapprove');
                $route->delete('{id}', 'delete')->name('delete');
            });
    });
