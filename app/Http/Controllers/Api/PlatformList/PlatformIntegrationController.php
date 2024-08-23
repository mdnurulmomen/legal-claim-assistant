<?php

namespace App\Http\Controllers\Api\PlatformList;

use App\Helpers\PlatformHandler;
use App\Http\Controllers\Api\PlatformList\Requests\AddOrEditIntegrationRequest;
use App\Http\Controllers\Api\PlatformList\Requests\IntegrationSettingRequest;
use App\Http\Controllers\Controller;
use App\Models\PlatformList;
use App\Services\Platform\PlatformIntegrationService;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlatformIntegrationController extends Controller
{
    use CommonTrait;

    /**
     * Returns the number formats in a multi-dimensional array format.
     *
     * @param Request $request
     * @return Response
     */
    public function numberFormats(Request $request): Response
    {
        $formats = PlatformHandler::$numberFormats;
        return withSuccess($this->convertToMultiDimensionalArray($formats));
    }

    /**
     * Returns the buyer types in a multi-dimensional array format.
     *
     * @param Request $request
     * @return Response
     */
    public function buyerTypes(Request $request): Response
    {
        $buyerTypes = PlatformHandler::$buyerTypes;
        return withSuccess($this->convertToMultiDimensionalArray($buyerTypes, isValueUpperCase: true));
    }

    /**
     * Returns the integration methods in a multi-dimensional array format.
     *
     * @param Request $request
     * @return Response
     */
    public function integrationMethods(Request $request): Response
    {
        $integrationMethods = PlatformHandler::$integrationMethods;
        return withSuccess($this->convertToMultiDimensionalArray($integrationMethods, isValueUpperCase: true));
    }

    /**
     * Returns the cap durations in a multi-dimensional array format.
     *
     * @param Request $request
     * @return Response
     */
    public function capDurations(Request $request): Response
    {
        $capDurations = PlatformHandler::$capDurations;
        return withSuccess($this->convertToMultiDimensionalArray($capDurations));
    }

    /**
     * Saves the integration settings for the given platform ID.
     *
     * @param IntegrationSettingRequest $request
     * @param int $platformId
     * @param PlatformIntegrationService $integrationService
     * @return Response
     */
    public function saveIntegration(IntegrationSettingRequest $request, int $platformId, PlatformIntegrationService $integrationService): Response
    {
        if(! isset($request->index) || $request->index < 0) {
            return withError('Invalid index');
        }

        $platform = PlatformList::select('id', 'integrations')->find($platformId);
        if(empty($platform)) {
            return withError('Platform not found');
        }

        try {
            $integrations = $integrationService->formatSettingData($request, $platform);
            $platform->integrations = $integrations;
            $platform->save();
            return withSuccess(message: 'Configuration saved successfully');
        } catch (\Throwable $th) {
            return withError($th->getMessage());
        }
    }

    public function storeIntegration(AddOrEditIntegrationRequest $request, int $platformId, PlatformIntegrationService $integrationService)
    {
        $platform = PlatformList::select('id', 'integrations')->find($platformId);
        if(empty($platform)) {
            return withError('Invalid platform Id provided');
        }

        $isExist = $integrationService->checkExists($request, $platform);
        if($isExist) {
            return withError('Integration already exists');
        }


    }
}
