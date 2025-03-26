<?php

use App\Library\Services\BestMatchSearch;
use App\Models\Integration;
use App\Models\IntegrationEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('Hello World!', 200, [
        'Content-Type' => 'text/plain',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'deny',
        'X-XSS-Protection' => '1; mode=block'
    ]);
});

Route::get('/test', function (Request $request) {
    $data = ['doe', 'jojhn', 'ojhn', 'jh'];
    $keyword = 'jh';

    $result = (new BestMatchSearch())->findBestMatch($data, $keyword);

    return response($result, 200, [
        'Content-Type' => 'text/plain',
    ]);
    // return response('Hello World test!', 200, [
    //     'Content-Type' => 'text/plain',
    //     'X-Content-Type-Options' => 'nosniff',
    //     'X-Frame-Options' => 'deny',
    //     'X-XSS-Protection' => '1; mode=block'
    // ]);
});
