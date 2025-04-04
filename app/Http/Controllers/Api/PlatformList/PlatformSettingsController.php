<?php

namespace App\Http\Controllers\Api\PlatformList;

use App\Http\Controllers\Api\PlatformList\Requests\PlatformSettingRequest;
use App\Http\Controllers\Controller;
use App\Jobs\ClearListCache;
use App\Models\PlatformList;
use App\Services\Platform\PlatformSettingService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PlatformSettingsController extends Controller
{
    /**
     * Saves the platform settings.
     *
     * @param PlatformSettingRequest $request
     * @param PlatformSettingService $settingService
     * @return Response
     */
    public function saveSettings(PlatformSettingRequest $request, PlatformSettingService $settingService): Response
    {
        $platform = PlatformList::find($request->platform_id);
        if (empty($platform)) {
            return withError('Invalid Platform Id Provided!');
        }

        try {
            DB::beginTransaction();

            $settingService->savePlatformData($request, $platform);
            $settingService->saveSettingOptions($request, $platform);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return withError('Platform Settings Saved Failed!');
        }

        ClearListCache::dispatch();

        return withSuccess(message: 'Platform Settings saved successfully');
    }
}
