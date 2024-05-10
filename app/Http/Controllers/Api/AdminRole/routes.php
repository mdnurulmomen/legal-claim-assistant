<?php

use App\Http\Controllers\Api\AdminRole\AdminRoleController;
use Illuminate\Support\Facades\Route;


Route::prefix('admin-role')->as('admin-role.')
    ->controller(AdminRoleController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('role-list', 'adminRoleList')->name('role.list');
        $route->get('all-roles', 'allAdminRoles')->name('role.all');
        $route->post('create-role', 'createAdminRole')->name('role.create');
        $route->get('show-role/{roleId}', 'showAdminRole')->name('role.show');
        $route->put('update-role/{roleId}', 'updateAdminRole')->name('role.update');
        $route->delete('delete-role/{roleId}', 'deleteAdminRole')->name('role.delete');
    });
