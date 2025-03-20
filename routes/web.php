<?php

use App\Models\Integration;
use App\Models\IntegrationEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return response('Hello World!', 200, [
        'Content-Type' => 'text/plain',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'deny',
        'X-XSS-Protection' => '1; mode=block'
    ]);
});

Route::get('/test', function (Request $request) {
    $integrationMail = IntegrationEmail::latest('id')->first();

    return view('mails.IntegrationMail', ['data' => $integrationMail]);

    // return response('Hello World test!', 200, [
    //     'Content-Type' => 'text/plain',
    //     'X-Content-Type-Options' => 'nosniff',
    //     'X-Frame-Options' => 'deny',
    //     'X-XSS-Protection' => '1; mode=block'
    // ]);
});
