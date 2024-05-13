<?php

namespace App\Http\Controllers\Api\PlatformList;

use App\Http\Controllers\Api\PlatformList\Resources\PlatformListResource;
use App\Http\Controllers\Controller;
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
        $orderBy = $request->input('order_by');
        $orderIn = $request->input('order_in');

        $platforms = PlatformList::query()
                        ->select('id', 'tag', 'name', 'campaign_name', 'source', 'status', 'created_at')
                        ->when(! empty($request->search_txt), function ($query) use ($request) {
                            return $query->where('tag', 'like', "%{$request->search_txt}%");
                        })
                        ->when(! empty($orderBy) && ! empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                            return $query->orderBy($orderBy, $orderIn);
                        }, function ($query) {
                            return $query->orderBy('id', 'desc');
                        })
                        ->paginate($limit);

        return withSuccessResourceList(PlatformListResource::collection($platforms));
    }

}
