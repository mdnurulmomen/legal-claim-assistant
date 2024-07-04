<?php

use App\Http\Controllers\Api\Lead\LeadController;
use Illuminate\Support\Facades\Route;


Route::prefix('excels')->as('excels.')
//    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->prefix('export')->as('export.')->group(function ($route) {
            $route->get('/leads', [LeadController::class, 'list'])->name('leads');
        });
    });
