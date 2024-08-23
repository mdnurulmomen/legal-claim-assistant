<?php

namespace App\Services\Platform;

use App\Models\PlatformList;
use Illuminate\Http\Request;

class PlatformIntegrationService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }


    /**
     * Formats configuration data for a given platform integration.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @return array
     * @throws \Illuminate\Http\Exceptions\HttpResponseException If the integration is not found.
     */
    public function formatSettingData(Request $request, PlatformList $platform): array
    {
        $validatedData = $request->validated();

        $integrations = $platform->integrations;

        $integration = $platform->integrations[$request->index] ?? null;
        if(empty($integration)) {
            abort(400, 'Integration not found');
        }

        $integrations[$request->index] = array_merge($integration, $validatedData);
        return $integrations;
    }

    public function checkExists(Request $request, PlatformList $platform): bool
    {
        $integrations = collect($platform->integrations);

        return $integrations->contains(function ($item) use ($request) {
                        return strtolower($item['name']) === strtolower($request->name);
                    });
    }
}
