<?php

use App\Http\Controllers\Api\Setting\GlobalPostbackController;
use App\Http\Controllers\Api\Setting\IntegratedMailController;
use Illuminate\Support\Facades\Route;


Route::prefix('integrated-email')->as('integrated.email.')
    ->controller(IntegratedMailController::class)
   ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('list', 'contentList')->name('content.list');
        $route->get('dropdown-list', 'dropdownList')->name('dropdown.list');
        $route->post('save-content', 'saveContent')->name('save.content');
        $route->get('show-content/{contentId}', 'showContent')->name('show.content');
        $route->delete('delete-content/{contentId}', 'deleteContent')->name('delete.content');
        $route->put('update-content/{contentId}', 'updateContent')->name('update.content');
    });


Route::prefix('settings/global-postback')->as('globalPostbacks.')
    ->controller(GlobalPostbackController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('index', 'globalPostbacks')->name('globalPostbacks');
        $route->get('show/{id}', 'globalPostback')->name('show.globalPostback');
        $route->put('update/{id}', 'update')->name('update');
        $route->post('create', 'create')->name('store');
        $route->delete('delete/{id}', 'delete')->name('delete');
        $route->get('post-back-events', 'getPostBackEvents')->name('post.back.events');
    });
