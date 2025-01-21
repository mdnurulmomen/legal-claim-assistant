<?php

use App\Http\Controllers\Api\CaseStudy\CaseStudyController;
use Illuminate\Support\Facades\Route;

Route::prefix('case-study')->as('newestcampaign.')
    ->controller(CaseStudyController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'index')->name('index');
        $route->post('create', 'create')->name('store');
        $route->get('show/{tag}', 'show')->name('show');
        $route->post('update/{tag}', 'update')->name('update');
        $route->delete('delete/{tag}', 'delete')->name('delete');
    });
