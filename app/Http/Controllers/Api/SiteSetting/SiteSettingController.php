<?php

namespace App\Http\Controllers\Api\SiteSetting;

use App\Http\Controllers\Api\SiteSetting\Requests\SiteSettingRequest;
use App\Http\Controllers\Api\SiteSetting\Resources\SiteSettingResource;
use App\Http\Controllers\Controller;
use App\Models\PageSetting;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SiteSettingController extends Controller
{
    /**
     * Retrieves site settings based on the provided slug.
     *
     * @param Request $request
     * @param string $slug
     * @return Response
     */
    public function getSiteSettings(Request $request, $slug): Response
    {
        $settings = PageSetting::where('page', $slug)->get();
        return withSuccess(SiteSettingResource::collection($settings));
    }

    /**
     * Save the site settings.
     *
     * @param SiteSettingRequest $request
     * @return Response
     */
    public function saveSiteSettings(SiteSettingRequest $request, SettingService $settingService): Response
    {
        $formattedData = $settingService->formatRequestData($request->validated());

        $setting = PageSetting::updateOrCreate(
                            ['page' => $request->page, 'type' => $request->type, 'user_id' => $formattedData['user_id']],
                            $formattedData
                        );

        return withSuccess(new SiteSettingResource($setting), 'Site setting saved successfully!');
    }
}
