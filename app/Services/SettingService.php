<?php

namespace App\Services;

use Illuminate\Http\Request;

class SettingService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Formats the request data by adding the 'user_id' key with the authenticated user's ID.
     *
     * @param array $requestData The request data to be formatted.
     * @return array The formatted request data.
     */
    public function formatRequestData(array $requestData): array
    {
        $requestData['user_id'] = auth()->id();
        return $requestData;
    }
}
