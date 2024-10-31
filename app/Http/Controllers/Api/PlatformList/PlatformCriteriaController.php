<?php

namespace App\Http\Controllers\Api\PlatformList;

use App\Http\Controllers\Controller;
use App\Models\PlatformList;
use App\Services\Platform\PlatformCriteriaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlatformCriteriaController extends Controller
{
    /**
     * Retrieves accepted criteria for a given platform based on the specified field.
     *
     * This method fetches all unique filter values and groups them by buyers
     * for the specified criteria field from the list of integrations of the given platform.
     * If the criteria field or platform is invalid, it returns a 400 status with an error message.
     *
     * @param Request $request The incoming request containing the criteria field.
     * @param int $platformId The ID of the platform to retrieve criteria for.
     * @param PlatformCriteriaService $criteriaService Service to handle platform criteria logic.
     * @return Response JSON response containing all criteria and buyer criteria, or an error message.
     */
    public function acceptedCriteria(Request $request, int $platformId, PlatformCriteriaService $criteriaService): Response
    {
        $fieldName = $request->criteria_field;
        $platformList = PlatformList::find($platformId);

        if (empty($fieldName) || empty($platformList)) {
            return response()->json([
                'status' => 400,
                'message' => 'Invalid Request.'
            ]);
        }

        $allCriteria = $criteriaService->getAllFilterValues($platformList->integrations, $fieldName);
        $buyersCriteria = $criteriaService->getBuyersGroupedByFilterValues($platformList->integrations, $fieldName);

        return withSuccess([
            'all_criteria' => $allCriteria,
            'buyers_criteria' => $buyersCriteria
        ]);
    }
}
