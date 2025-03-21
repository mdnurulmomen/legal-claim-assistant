<?php

namespace App\Services\Platform;

use App\Helpers\Utility;
use App\Models\Caps;
use App\Models\GlobalLog;
use App\Models\Integration;
use App\Models\PlatformList;
use App\Services\GlobalLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
     * @param string $name
     * @return array
     * @throws \Illuminate\Http\Exceptions\HttpResponseException If the integration is not found.
     */
    public function formatSettingData(Request $request, PlatformList $platform, string $name): array
    {
        $integrations = $platform->integrations ?? [];

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

        if($requestData['custom_maps'] ?? null){
            $requestData['custom_maps'] = $this->replaceStringWithBool($requestData['custom_maps'], 'custom_maps');
        }

        if($requestData['maps'] ?? null){
            $requestData['maps'] = $this->replaceStringWithBool($requestData['maps']);
        }

        $ping = $requestData['ping'] ?? null;

        if($ping && array_key_exists('save_data', $ping)) {
            $ping['save_data'] = $this->convertNullToString($ping['save_data']);
        }

        if($ping && array_key_exists('triggers', $ping)) {
            $ping['triggers'] = $this->convertNullToString($ping['triggers']);
        }

        $requestData['ping'] = $ping;

        if(empty($requestData['ping'])) {
            unset($requestData['ping']);
        }

        $brandData = $requestData['brand_data'] ?? null;
        $requestData['brand_data'] = $brandData;

        if(empty($requestData['brand_data'])) {
            unset($requestData['brand_data']);
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

        if(empty($ping) && array_key_exists('ping', $integration)) {
            unset($integration['ping']);
        }

        if(empty($brandData) && array_key_exists('brand_data', $integration)) {
            unset($integration['brand_data']);
        }

        $integrations[$index] = array_merge($integration, $requestData);
        return $integrations;
    }

    /**
     * Adds or updates an integration for the given platform ID and name.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @param string $name
     * @return void
     */
    public function addOrUpdateIntegration(Request $request, PlatformList $platform, string $name)
    {
        $integration = Integration::firstOrNew([
                            'list_id' => $platform->id,
                            'buyer_unique_id' => $name,
                        ]);

        $integration->list_id = $platform->id;
        $integration->buyer_id = $request->buyer_profile;
        $integration->name = $name;
        $integration->buyer_unique_id = $name;
        $integration->type = $request->buyer_type;
        $integration->buyer_headers = $request->save_data;
        $integration->lead_id_key = $request->lead_id_key;
        $integration->save();
    }

    /**
     * Converts null values in the given data array to empty strings.
     *
     * @param array $data The data array to convert.
     * @return array The converted data array.
     */
    public function convertNullToString($data) {
        return array_map(function ($value) {
            return $value === null || $value === 'null' ? '' : $value;
        }, $data);
    }

    /**
     * Formats CV triggers data for a given platform integration.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @param string $name
     * @return array
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public function formatTriggersData(Request $request, PlatformList $platform, string $name): array
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
    public function replaceStringWithBool($array, $type = null): array
    {

        $updatedArray = [];

        foreach ($array as $key => $value) {

            if (is_array($value)) {
                $updatedArray[$key] = $this->replaceStringWithBool($value);
                continue;
            }

            $newValue = match (true) {
                strcasecmp($value, 'true') === 0 => true,     // Boolean true
                strcasecmp($value, 'false') === 0 => false,   // Boolean false
                strcasecmp($value, 'null') === 0 => null,     // Null
                strcasecmp($value, 'undefined') === 0 => null, // Null
                is_numeric($value) => ((ctype_digit($value) && (((string) $value)[0]) === '0')
                        ? $value
                        : (strpos($value, '.') === false ? (int) $value : (float) $value)),

                default => $value
            };

            $updatedArray[$key] = $newValue;
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

    /**
     * Inserts or deletes cap history records for a given platform integration.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @param string $name
     */
    public function insertOrDeleteCapsHistory(Request $request, PlatformList $platform, string $name): void
    {
        $listIntegration = collect($platform->integrations)->firstWhere('name', $name);
        $caps = $listIntegration['caps'] ?? [];
        $buyerId = (int) $listIntegration['buyer_profile'];

        $integration = Integration::query()
                            ->where('list_id', $platform->id)
                            ->where('buyer_id', $buyerId)
                            ->where('buyer_unique_id', $name)
                            ->first();

        if(empty($integration)) return;

        Caps::where('integration_id', $integration->id)
                ->where('buyer_id', $buyerId)
                ->where('list_id', $platform->id)
                ->whereNull('end_date')
                ->delete();

        $capsObject = [];
        $now = now();

        foreach ($caps as $cap) {

            if(! $cap['active']) continue;

            $capsObject[] = [
                'list_id' => $platform->id,
                'integration_id' => $integration->id,
                'buyer_id' => $buyerId,
                'caps' => json_encode($cap),
                'status' => 'Active',
                'column_scope' => $this->formatColumnScope($cap['column']),
                'cap_amount' => (float) $cap['amount'],
                'duration' => $cap['duration'],
                'start_date' => $this->generateCapsDate($cap['duration']),
                'end_date' => null,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }

        Caps::insert($capsObject);
    }

    /**
     * Generates a Carbon date object based on the given duration.
     *
     * @param string|null $duration The duration type, which can be 'daily' or 'weekly'.
     * @return \Illuminate\Support\Carbon|null
     */
    public function generateCapsDate(?string $duration) : Carbon | null
    {

        $timezone = 'America/New_York';

        if ($duration === 'daily') {
            return Carbon::now($timezone)->startOfDay();
        }

        if ($duration === 'weekly') {
            return Carbon::now($timezone)->startOfWeek();
        }

        return null;
    }

    /**
     * Returns a string representation of a column scope array in the format "column:value".
     * If the array is empty, returns null.
     *
     * @param array $columns Array of column => value pairs.
     * @return string|null
     */
    public function formatColumnScope(array | null $columns): ?string
    {
        if(empty($columns)) return 'None';

        $key = key($columns);
        $value = $columns[$key];
        return "{$key}:{$value}";
    }

    /**
     * Removes an integration and its associated CV trigger from a given platform.
     *
     * @param Request $request
     * @param PlatformList $platform
     * @param string $slug
     */
    public function removeIntegrationAndCvTrigger(Request $request, PlatformList $platform, $slug): void
    {
        $integrations = collect($platform->integrations);
        $cvTriggers = collect($platform->cv_trigger ?? []);

        $index = $integrations->search(function ($item) use ($slug) {
                    return strtolower($item['name']) === strtolower($slug);
                });

        if($index === false) {
            abort(400, 'Integration not found!');
        }

        $triggerIndex = $cvTriggers->search(function ($item) use ($slug) {
                            return strtolower($item['name']) === strtolower($slug);
                        });

        $oldIntegration = $integrations->get($index);
        $oldTrigger = $triggerIndex !== false ? $cvTriggers->get($triggerIndex) : null;

        $integrations->forget($index);
        $cvTriggers->forget($triggerIndex);

        $platform->integrations = $integrations->values()->toArray();
        $platform->cv_trigger = $cvTriggers->values()->toArray();
        $platform->save();

        $this->saveIntegrationTriggerData($platform->id, $oldIntegration, $oldTrigger);
    }

    /**
     * Saves integration trigger data for a given platform ID.
     *
     * @param int $platformId The ID of the platform.
     * @param array $integration The integration data.
     * @param array $cvTrigger The CV trigger data.
     */
    public function saveIntegrationTriggerData(int $platformId, array $integration, array $cvTrigger): void
    {
        $data = [
            'loggable_type' => Utility::$aliasLogTypes['integration_trigger'],
            'loggable_id' => $platformId,
            'data' => [
                'name' => $integration['name'],
                'integration' => $integration,
                'cv_trigger' => $cvTrigger
            ]
        ];

        (new GlobalLogService())->createLog($data);
    }

    /**
     * Restores an integration and its associated CV trigger to a given platform.
     *
     * @param Request $request
     * @param GlobalLog $log
     * @param PlatformList $platform
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException If the integration or CV trigger already exists.
     *
     * @return void
     */
    public function formatAndRestoreIntegration(Request $request, GlobalLog $log, PlatformList $platform): void
    {
        $integration = $log->data['integration'];
        $cvTrigger = $log->data['cv_trigger'];
        $name = $log->data['name'];

        $integrations = $platform->integrations;
        $cvTriggers = $platform->cv_trigger;

        $index = collect($integrations)->search(function ($item) use ($name) {
            return strtolower($item['name']) === strtolower($name);
        });

        $triggerIndex = collect($cvTriggers)->search(function ($item) use ($name) {
            return strtolower($item['name']) === strtolower($name);
        });

        if($index > -1) {
            abort(400, 'Integration already exists');
        }

        if($triggerIndex > -1) {
            abort(400, 'CV Trigger already exists');
        }

        $integrations[] = $integration;
        $cvTriggers[] = $cvTrigger;

        $platform->integrations = $integrations;
        $platform->cv_trigger = $cvTriggers;
        $platform->save();

        $log->delete();
    }
}
