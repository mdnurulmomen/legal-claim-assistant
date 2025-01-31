<?php

use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('user')->as('user.')
    ->controller(UserController::class)
    ->middleware('auth:sanctum')
    ->group(function ($route) {
        $route->get('user-list', 'userList')->name('user.list');
        $route->post('create-user', 'createUser')->name('user.create');
        $route->get('show-user/{userId}', 'showUser')->name('user.show');
        $route->put('update-user/{userId}', 'updateUser')->name('user.update');
        $route->delete('delete-user/{userId}', 'deleteUser')->name('user.delete');
        $route->post('update-status/{userId}', 'updateStatus')->name('update.status');

        $route->post('update-my-info', 'updateMyInfo')->name('update-my-info');
        $route->post('update-my-email', 'updateMyEmail')->name('update-my-email');
        $route->post('update-my-password', 'updateMyPassword')->name('update-my-password');

        $route->get('partner-list', 'partnerList')->name('partner.list');
        $route->post('manager-list', 'managerList')->name('manager.list');
    });
