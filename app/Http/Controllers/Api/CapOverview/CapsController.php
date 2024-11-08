<?php

namespace App\Http\Controllers\Api\CapOverview;

use App\Http\Controllers\Api\CapOverview\Resources\CapsResource;
use App\Http\Controllers\Controller;
use App\Services\CapsService;
use App\Traits\CommonTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CapsController extends Controller
{
    use CommonTrait;

    /**
     * @param Request $request
     * @param CapsService $capsService
     * @return Response
     */
    public function capsList(Request $request, CapsService $capsService): Response
    {

        $caps = $capsService->getCapsList($request);

        $caps = $caps->paginate($request->limit, ['*'], 'page', $request->page);

        return withSuccessResourceList(CapsResource::collection($caps));
    }

    /**
     *
     * @param Request $request
     * @param CapsService $capsService
     * @return JsonResponse
     */
    public function getCapacities(Request $request, CapsService $capsService): JsonResponse
    {
        $capacities = $capsService->getCapacities($request);

        return response()->json($capacities);
    }

}
