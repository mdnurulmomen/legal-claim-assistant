<?php

use App\Http\Controllers\Api\IntegratedMail\IntegratedMailController;
use Illuminate\Support\Facades\Route;


Route::prefix('integrated-email')->as('integrated.email.')
    ->controller(IntegratedMailController::class)
   ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->post('save-content', 'saveContent')->name('save.content');
    });
