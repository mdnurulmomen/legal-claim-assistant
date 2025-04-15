<?php

namespace App\Http\Controllers\Api\Alert;

use App\Http\Controllers\Api\Alert\Resources\LogsResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Http;

class LogsController extends Controller
{
    public function retrieveLogs(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $currentPage = $request->input('page', 1);

        // Get authenticated user token from db
        $user = auth()->user();
        $token = $user->api_token;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Cookie' => $request->header('Cookie'),
        ])->get("https://monetize.affimedia.nl/api/v1/alert/logs", [
            'page' => $currentPage,
            'per_page' => $perPage
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return withSuccessResourceList(new ResourceCollection([new LogsResource($data)]));
        } else {
            return response()->json(['error' => 'Failed to retrieve logs'], $response->status());
        }
    }
}
