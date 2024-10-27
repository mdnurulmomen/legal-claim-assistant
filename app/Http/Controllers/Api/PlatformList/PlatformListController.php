<?php

namespace App\Http\Controllers\Api\PlatformList;

use App\Http\Controllers\Api\PlatformList\Resources\PlatformListResource;
use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\Integration;
use App\Models\PlatformList;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlatformListController extends Controller
{

    /**
     * Retrieves a list of platforms based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function platformList(Request $request): Response
    {
        $limit = $request->input('limit', 10);
        $orderBy = $request->input('order_by') == 'total_leads' ? 'total' : $request->input('order_by');
        $orderIn = $request->input('order_in');
        $platformSourceIds = $request->input('platform_source_ids', []);

        $platforms = PlatformList::query()
                        ->select('id', 'tag', 'name', 'total', 'campaign_name', 'options', 'source', 'status', 'updated_at', 'created_at')
                        ->when(! empty($request->search_txt), function ($query) use ($request) {
                            return $query->whereAny([
                                'name',
                                'campaign_name',
                                'source'
                            ], 'like', '%' . $request->search_txt . '%');
                        })
                        ->when(! empty($request->status), function ($query) use ($request) {
                            return $query->where('status', $request->status);
                        })
                        ->when(! empty($platformSourceIds) && is_array($platformSourceIds), function ($query) use ($platformSourceIds) {
                            return $query->whereIn('id', $platformSourceIds);
                        })
                        ->when(! empty($orderBy) && ! empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                            return $query->orderBy($orderBy, $orderIn);
                        }, function ($query) {
                            return $query->orderBy('updated_at', 'desc');
                        })
                        ->paginate($limit);

        return withSuccessResourceList(PlatformListResource::collection($platforms));
    }

    /**
     * Retrieves a list of sources based on the search text provided in the request.
     *
     * @param Request $request
     * @return Response
     */
    public function getSourceList(Request $request): Response
    {
        $platformSource = PlatformList::query()
                            ->select('id as value', 'source as label')
                            ->when(! empty($request->search_txt), function ($query) use ($request) {
                                return $query->where('source', 'like', "%{$request->search_txt}%");
                            })
                            ->limit(100)
                            ->get();

        return withSuccess($platformSource);
    }

    public function buyerList(Request $request, int $platformId): Response
    {
        $buyers = Buyer::query()
                            ->select('id as value', 'name as label')
                            ->get();

        return withSuccess($buyers);
    }

    /**
     * Retrieves a platform by its ID.
     *
     * @param Request $request
     * @param int $platformId
     * @return Response
     */
    public function showPlatform(Request $request, int $platformId): Response
    {
        $platform = PlatformList::query()
                        ->select(
                            'id',
                            'tag',
                            'name',
                            'total',
                            'campaign_name',
                            'options',
                            'source',
                            'status',
                            'cv_trigger',
                            'integrations',
                            'lead_headers',
                            'options',
                            'updated_at',
                            'created_at'
                        )
                        ->find($platformId);

        if(empty($platform)) {
            return withError('Platform not found');
        }

        return withSuccess(new PlatformListResource($platform));
    }

}
