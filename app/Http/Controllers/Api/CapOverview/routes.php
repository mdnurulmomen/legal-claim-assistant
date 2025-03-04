<?php

use App\Http\Controllers\Api\CapOverview\CapsController;
use Illuminate\Support\Facades\Route;


Route::prefix('/caps/overview/')->as('caps.')
    ->controller(CapsController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('list', 'capsList')->name('list');
        $route->post('capacities', 'getCapacities')->name('capacities');
        $route->post('all-capacities', 'allCapacities')->name('all.capacities');
    });
