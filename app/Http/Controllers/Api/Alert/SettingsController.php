<?php

namespace App\Http\Controllers\Api\Alert;

use App\Http\Controllers\Api\Alert\Resources\RulesResource;
use App\Http\Controllers\Api\Alert\Resources\SettingsResource;
use App\Http\Controllers\Controller;
use App\Models\PlatformList;
use App\Models\User;
use App\Services\Alert\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    // load settings
    public function settings(Request $request)
    {
        // load settings from database currently we are using column saved in site_settings table
        $alert_settings = DB::table('site_settings')->first();

        // Check if alert_settings is null
        if (!$alert_settings) {
            return response()->json(['error' => 'Settings not found'], 404);
        }

        // extract settings from json column 
        $platform_lists = json_decode($alert_settings->platform_lists, true);

        // Check if platform_lists is null or doesn't contain expected keys
        if (!$platform_lists || !isset($platform_lists['via'], $platform_lists['status'], $platform_lists['check_period'], $platform_lists['check_interval'])) {
            return response()->json(['error' => 'Invalid settings format'], 400);
        }

        // return only needed settings 
        $settings = [
            'via' => $platform_lists['via'],
            'status' => $platform_lists['status'],
            'check_period' => $platform_lists['check_period'],
            'check_interval' => $platform_lists['check_interval']
        ];

        // Create a ResourceCollection with a single item
        $resourceCollection = new ResourceCollection([new SettingsResource($settings)]);

        // return settings in json format 
        return withSuccessResourceList($resourceCollection);
    }

    // update settings
    public function updateSettings(Request $request)
    {
        // Validate input
        $validatedData = $request->validate([
            'via' => 'required|array',
            'via.*' => 'required|string|in:email,app,sms',
            'status' => 'required|string|in:active,inactive',
            'check_period' => 'required|array',
            'check_period.label' => 'required|string|in:minutes,hours,days',
            'check_period.value' => 'required|integer|min:1',
            'check_interval' => 'required|array',
            'check_interval.label' => 'required|string|in:minutes,hours,days',
            'check_interval.value' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($validatedData) {
                $siteSettings = DB::table('site_settings')->lockForUpdate()->first();

                if (!$siteSettings) {
                    throw new \Exception('Site settings not found');
                }

                $platformLists = json_decode($siteSettings->platform_lists, true) ?? [];

                // Update only specific keys
                $platformLists = array_merge($platformLists, [
                    'via' => $validatedData['via'],
                    'status' => $validatedData['status'],
                    'check_period' => $validatedData['check_period'],
                    'check_interval' => $validatedData['check_interval'],
                ]);

                DB::table('site_settings')
                    ->where('id', $siteSettings->id)
                    ->update(['platform_lists' => json_encode($platformLists)]);
            });

            return withSuccess([], 'Alert Settings updated successfully');
        } catch (\Exception $e) {
            return withError($e->getMessage());
        }
    }

    // load rules
    public function rules(Request $request)
    {
        // load rules from same column in site_settings table only select rules key
        $rules = DB::table('site_settings')->first();
        $rules = json_decode($rules->platform_lists, true);

        // all possible receivers
        $admins = User::where(['role' => 'admin', 'status' => 1])->select('id', 'name')->get();
        $rules['all_possible_receivers'] = $admins;

        // all possible lists
        $lists = PlatformList::where('status', "Active")->select('id', 'name')->get();
        $rules['all_possible_lists'] = $lists;

        // Create a ResourceCollection with a single item
        $resourceCollection = new ResourceCollection([new RulesResource($rules)]);

        // return settings in json format 
        return withSuccessResourceList($resourceCollection);
    }

    // update rules
    public function updateRules(Request $request)
    {
        // init settings service
        $settingsService = new SettingsService('platform_lists');

        // create validation rules for the request
        $rules = [
            'ignored_lists' => 'array|nullable',
            'recivers' => 'array|nullable',
            'status' => 'required|string|in:active,inactive',
            'name' => 'required|string',
            'id' => 'required|string',
            'buyerResponseMatching' => 'array|nullable',
            'ignoreParameters' => 'array|nullable',
            'safeResponse' => 'array|nullable',
        ];

        // validate input 
        $validation =  Validator::make(
            $request->all(),
            $rules
        );

        if ($validation->fails()) {
            return withError($validation->errors()->first());
        }

        $settingsService->updateRule($request->id, $request->all());

        // return the same data as for load rules
        $rules = DB::table('site_settings')->first();
        $rules = json_decode($rules->platform_lists, true);

        // all possible receivers
        $rules['all_possible_receivers'] = [];

        // all possible lists
        $rules['all_possible_lists'] = [];

        // Create a ResourceCollection with a single item
        $resourceCollection = new ResourceCollection([new RulesResource($rules)]);

        // return settings in json format 
        return withSuccessResourceList($resourceCollection);
    }

    // load logs
    public function logs(Request $request)
    {
        // $logs = AlertLog::all();
        // return response()->json($logs);
    }
}
