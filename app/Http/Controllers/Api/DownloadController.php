<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\Controller;

class DownloadController extends Controller
{

    /*
     * Download single  attachment
     * @return Illuminate\Support\Facades\Response
     */
    function attachmentDownload (Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', -1);

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
    }

    /*
     * Download zip  attachment
     * @return Illuminate\Support\Facades\Response
     */
    function attachmentZip (Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', -1);

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
    }
}
