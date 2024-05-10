<?php

use App\Http\Controllers\Api\Permission\PermissionController;
use Illuminate\Support\Facades\Route;


Route::prefix('permission')->as('permission.')
    ->controller(PermissionController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('menus/{roleId}', 'getMenus')->name('menus');
        $route->post('update-permission/{roleId}', 'updatePermission')->name('update-permission');
        // $route->get('get-permission', 'getPermission')->name('get-permission');
    });

Route::get('/permission/get-permission', [PermissionController::class, 'getPermission'])->name('permission.get-permission');
