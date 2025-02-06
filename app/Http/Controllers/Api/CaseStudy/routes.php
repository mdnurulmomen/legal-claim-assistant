<?php

use App\Http\Controllers\Api\CaseStudy\CaseStudyController;
use App\Http\Controllers\Api\CaseStudy\CaseStudyCategoryController;
use App\Http\Controllers\Api\CaseStudy\CaseStudyTagController;
use Illuminate\Support\Facades\Route;

Route::prefix('case-study')->as('caseStudy.')
    ->controller(CaseStudyController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'index')->name('index');
        $route->post('create', 'create')->name('store');
        $route->get('show/{tag}', 'show')->name('show');
        $route->post('update/{tag}', 'update')->name('update');
        $route->delete('delete/{tag}', 'delete')->name('delete');
    });

Route::prefix('case-study')->as('caseStudyCat.')
    ->controller(CaseStudyCategoryController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('category/index', 'index')->name('index');
        $route->get('category/select', 'select')->name('select');
        $route->post('category/create', 'create')->name('store');
        $route->get('category/show/{tag}', 'show')->name('show');
        $route->post('category/update/{tag}', 'update')->name('update');
        $route->delete('category/delete/{tag}', 'delete')->name('delete');
    });

Route::prefix('case-study')->as('caseStudyTag.')
    ->controller(CaseStudyTagController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('tag/index', 'index')->name('index');
        $route->get('tag/select', 'select')->name('select');
        $route->post('tag/create', 'create')->name('store');
        $route->get('tag/show/{tag}', 'show')->name('show');
        $route->post('tag/update/{tag}', 'update')->name('update');
        $route->delete('tag/delete/{tag}', 'delete')->name('delete');
    });
