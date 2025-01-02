<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

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


Route::get('/attachment-download', function (Request $request) {

    if($request->has(('files') && !empty($request->file))){
        return response()->json(['error' => 'No URL provided'], 400);
    }

    $file = Storage::disk('s3')->get($request->file);
    $mimeType = Storage::disk('s3')->mimeType($request->file);
    $headers = [
        'Content-Type'        => $mimeType,
        'Content-Disposition' => 'attachment; filename="'.basename($request->file).'"',
    ];
    return Response::make($file, 200, $headers);
});

Route::get('/attachment-zip', function (Request $request) {

    if($request->has(('file_paths') && !empty($request->file_paths))){
        return response()->json(['error' => 'No URL provided'], 400);
    }

    $s3 = Storage::disk('s3');

    // List of S3 file paths to include in the ZIP
    $filesToDownload = explode(',', $request->file_paths);

    $zipFileName = ($request->filename && !empty($request->filename))? $request->filename.'_attachments.zip' : 'files.zip';
    $tempFile = tempnam(sys_get_temp_dir(), 'zip');

    $zip = new \ZipArchive;

    if ($zip->open($tempFile, \ZipArchive::CREATE) === TRUE) {
        foreach ($filesToDownload as $filePath) {
            // Get file content from S3
            $fileContent = $s3->get($filePath);

            // Add the file to the ZIP
            $fileName = basename($filePath);
            $zip->addFromString($fileName, $fileContent);
        }
        $zip->close();
    } else {
        return response()->json(['error' => 'Unable to create ZIP file'], 500);
    }

    // Return the ZIP file as a downloadable response
    return response()->download($tempFile, $zipFileName)->deleteFileAfterSend(true);
});
