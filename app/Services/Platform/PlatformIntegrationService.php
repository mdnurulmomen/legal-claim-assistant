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
        $name = str()->slug($request->name, '_');

        $integrations = $platform->integrations;

        $index = collect($integrations)->search(function ($item) use ($name) {
            return strtolower($item['name']) === strtolower($name);
        });

        if($this->isDuplicateName($name, $integrations, $index)) {
            abort(400, 'Integration name already exists');
        }

        $requestData = $request->all();
        $requestData['name'] = $name;

        if($index === false) {
            $requestData['order'] = count($integrations) + 1;

            $integrations[] = $requestData;
            return $integrations;
        }

        $integration = $platform->integrations[$index] ?? null;
        if(empty($integration)) {
            abort(400, 'Integration not found');
        }

        $integrations[$index] = array_merge($integration, $requestData);
        return $integrations;
    }

    public function formatIntegrationOrderData(Request $request, PlatformList $platform)
    {
        $requestedIntegrations = collect($request->integrations)->groupBy('name')->all();

        $integrations = $platform->integrations;

        foreach ($integrations as &$integration) {

            $newIntegration = $requestedIntegrations[$integration['name']][0] ?? null;
            if(empty($newIntegration)) continue;

            $integration['active'] = $newIntegration['active'];
            $integration['order'] = $newIntegration['order'];
        }

        return $integrations;
    }

    /**
     * Checks if the given integration name already exists in the given list of integrations.
     *
     * @param string $name
     * @param array $integrations
     * @return bool
     */
    public function isDuplicateName(string $name, array $integrations, int | null $oldIndex = -1): bool
    {
        $formattedIntegrations = collect($integrations);

        if($oldIndex > -1) {
           unset($formattedIntegrations[$oldIndex]);
        }

        return $formattedIntegrations->contains(function ($item) use ($name) {
            return strtolower($item['name']) === strtolower($name);
        });
    }

    public function checkExists(Request $request, PlatformList $platform): bool
    {
        $integrations = collect($platform->integrations);

        return $integrations->contains(function ($item) use ($request) {
                        return strtolower($item['name']) === strtolower($request->name);
                    });
    }
}
