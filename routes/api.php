<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\Api\DownloadController;

// include('app/Http/Controllers/Api/Auth/routes.php');
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/proxy', function (Request $request) {
    // Get the target URL from the query parameter
    $url = $request->query('url');

    // If the URL is not provided, return a bad request response
    if (!$url) {
        return response()->json(['error' => 'No URL provided'], 400);
    }

    try {
        // Use Laravel's HTTP client to make a request to the target URL
        $response = Http::get($url);

        // Return the response from the target URL
        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type'));
    } catch (\Exception $e) {
        // Handle errors (such as if the target URL is down)
        return response()->json(['error' => 'Unable to fetch the URL'], 500);
    }
});


Route::get('/attachment-download', [DownloadController::class, 'attachmentDownload']);

Route::get('/attachment-zip', [DownloadController::class, 'attachmentZip']);
