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

        if (array_key_exists('cv_trigger', $requestData)) {
            unset($requestData['cv_trigger']);
        }

        if($requestData['ping']['custom_params'] ?? null) {
            $requestData['ping']['custom_params'] = $this->replaceStringWithBool($requestData['ping']['custom_params']);
        }

        if($requestData['custom_params'] ?? null){
            $requestData['custom_params'] = $this->replaceStringWithBool($requestData['custom_params']);
        }

        if($requestData['filter'] ?? null){
            $requestData['filter'] = $this->replaceStringWithBool($requestData['filter']);
        }

        if($requestData['convert_maps'] ?? null){
            $requestData['convert_maps'] = $this->replaceStringWithBool($requestData['convert_maps']);
        }

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

    /**
     * Formats CV triggers data for a given platform integration.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @return array
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public function formatTriggersData(Request $request, PlatformList $platform): array
    {
        $name = str()->slug($request->name, '_');
        $cvTriggers = $platform->cv_trigger;
        $requestedTrigger = $request->cv_trigger;
        $requestedTrigger['name'] = $name;

        $index = collect($cvTriggers)->search(function ($item) use ($name) {
            return strtolower($item['name']) === strtolower($name);
        });

        if($index === false) {
            $cvTriggers[] = $requestedTrigger;
            return $cvTriggers;
        }

        $cvTrigger = $cvTriggers[$index] ?? null;
        if(empty($cvTrigger)) {
            abort(400, 'Trigger not found');
        }

        $cvTriggers[$index] = array_merge($cvTrigger, $requestedTrigger);
        return $cvTriggers;
    }

    /**
     * Formats the order of integrations based on the given request data and platform.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @return array
     */
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


    /**
     * Checks if the given integration name already exists in the given list of integrations.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @return bool
     */
    public function checkExists(Request $request, PlatformList $platform): bool
    {
        $integrations = collect($platform->integrations);

        return $integrations->contains(function ($item) use ($request) {
                        return strtolower($item['name']) === strtolower($request->name);
                    });
    }

    /**
     * Replaces "null", "true", and "false" strings with their corresponding null, true, and false values.
     * Recursively replaces values in nested arrays.
     *
     * @param array $array The array to replace string values in.
     * @return array The array with string values replaced by their corresponding values.
     */
    public function replaceStringWithBool($array): array
    {
        $updatedArray = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $updatedArray[$key] = $this->replaceStringWithBool($value); // Recursive call for nested arrays
            } elseif ($value === "null") {
                $updatedArray[$key] = null; // Replace "null" string with null value
            } elseif ($value === "true") {
                $updatedArray[$key] = true; // Replace "true" string with true value
            } elseif ($value === "false") {
                $updatedArray[$key] = false; // Replace "false" string with false value
            } else {
                $updatedArray[$key] = $value; // Copy the original value
            }
        }
        return $updatedArray;
    }

    /**
     * Splits string values in an array on ':' and reorganizes the array to have the string before the colon as the key
     * and the string after the colon as the value.
     *
     * @param array $array
     * @return array
     */
    public function splitColon($array): array
    {
        $updatedArray = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $updatedArray[$key] = $this->splitColon($value);
            } elseif (strpos($value, ':') !== false) {
                $value = explode(':', $value);
                $updatedArray["column"][$value[0]] = $value[1]; // Replace "null" string with null value
            } else {
                $updatedArray[$key] = $value; // Copy the original value
            }
        }
        return $updatedArray;
    }
}
