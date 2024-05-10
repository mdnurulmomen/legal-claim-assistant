<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// include('app/Http/Controllers/Api/Auth/routes.php');
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
