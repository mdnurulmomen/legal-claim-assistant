<?php

namespace App\Http\Controllers\Api\PlatformList;

use App\Helpers\PlatformHandler;
use App\Http\Controllers\Api\PlatformList\Requests\AddOrEditIntegrationRequest;
use App\Http\Controllers\Api\PlatformList\Requests\IntegrationSettingRequest;
use App\Http\Controllers\Controller;
use App\Models\GlobalLog;
use App\Models\PlatformList;
use App\Services\Platform\PlatformIntegrationService;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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
        return withSuccess($this->convertToMultiDimensionalArray($integrationMethods));
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
     * Saves the full integration data for the given platform ID.
     *
     * @param IntegrationSettingRequest $request
     * @param int $platformId
     * @param PlatformIntegrationService $integrationService
     * @return Response
     */
    public function saveFullIntegration(IntegrationSettingRequest $request, int $platformId, PlatformIntegrationService $integrationService)
    {
        $platform = PlatformList::select('id', 'integrations', 'cv_trigger')->find($platformId);
        if(empty($platform)) {
            return withError('Platform not found');
        }

        try {
            DB::beginTransaction();

            $name = str()->slug($request->name, '_');

            $integrations = $integrationService->formatSettingData($request, $platform, $name);
            $cvTriggers = $integrationService->formatTriggersData($request, $platform, $name);

            $platform->integrations = $integrations;
            $platform->cv_trigger = $cvTriggers;
            $platform->save();

            $integrationService->insertOrDeleteCapsHistory($request, $platform, $name);
            $integrationService->addOrUpdateIntegration($request, $platform, $name);

            DB::commit();
            return withSuccess(message: 'Integration saved successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return withError($th->getMessage());
        }
    }

    /**
     * Updates the integration order and status for the given platform ID.
     *
     * @param Request $request
     * @param int $platformId
     * @param PlatformIntegrationService $integrationService
     * @return Response
     */
    public function updateIntegration(Request $request, int $platformId, PlatformIntegrationService $integrationService)
    {
        $platform = PlatformList::select('id', 'integrations')->find($platformId);
        if(empty($platform)) {
            return withError('Platform not found');
        }

        $formattedData = $integrationService->formatIntegrationOrderData($request, $platform);
        $platform->integrations = $formattedData;
        $platform->save();
        return withSuccess(message: 'Integration order and status updated successfully');
    }

    /**
     * Stores a new integration for the given platform ID.
     *
     * @param AddOrEditIntegrationRequest $request
     * @param int $platformId
     * @param PlatformIntegrationService $integrationService
     * @return Response
     */
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

    /**
     * Deletes an integration for the given platform ID and slug.
     *
     * @param Request $request
     * @param int $platformId
     * @param string $slug
     * @param PlatformIntegrationService $integrationService
     * @return Response
     */
    public function deleteIntegration(Request $request, int $platformId, string $slug, PlatformIntegrationService $integrationService): Response
    {
        $platform = PlatformList::select('id', 'integrations', 'cv_trigger')->find($platformId);
        if(empty($platform)) {
            return withError('Platform not found');
        }

        try {
            DB::beginTransaction();
            $integrationService->removeIntegrationAndCvTrigger($request, $platform, $slug);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return withError('Failed to delete integration! ' . $th->getMessage());
        }

        return withSuccess(message: 'Integration archived successfully!');
    }

    /**
     * Restores a deleted global log of integration type.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function restoreIntegration(Request $request, int $id, PlatformIntegrationService $integrationService): Response
    {
        $log = GlobalLog::find($id);
        if (empty($log)) {
            return withError('Log not found');
        }

        $platform = PlatformList::select('id', 'integrations', 'cv_trigger')->find($log->loggable_id);
        if(empty($platform)) {
            return withError('Platform not found');
        }

        try {
            DB::beginTransaction();
            $integrationService->formatAndRestoreIntegration($request, $log, $platform);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return withError('Failed to restore integration! ' . $th->getMessage());
        }

        return withSuccess(message: 'Integration restored successfully!');
    }
}
