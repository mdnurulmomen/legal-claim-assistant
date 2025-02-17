<?php

use App\Http\Controllers\Api\CreativeUpload\CreativeUploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('creative')->as('creative.')
    ->controller(CreativeUploadController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'index')->name('index');
        $route->get('show/{id}', 'show')->name('show');
        $route->post('update/{id}', 'update')->name('show');
        $route->get('approved/{id}', 'approved')->name('approved');
        $route->get('rejected/{id}', 'rejected')->name('rejected');
        // $route->post('testUpload', 'postUpload')->name('postUpload');
    });
