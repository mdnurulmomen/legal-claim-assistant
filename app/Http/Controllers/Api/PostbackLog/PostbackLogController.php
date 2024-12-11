<?php

namespace App\Http\Controllers\Api\PostbackLog;

use App\Helpers\SettingHandler;
use App\Helpers\Utility;
use App\Http\Controllers\Api\PostbackLog\Resources\PostbackLogResource;
// use App\Http\Controllers\Api\PostbackLog\Resources\SingleGlobalPostbackResource;
use App\Http\Controllers\Controller;
use App\Models\PostbackLog;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;

class PostbackLogController extends Controller
{
    use CommonTrait;

    /**
     * Retrieves a list of Poatback based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);
        $orderBy = $request->input('order_by');
        $orderIn = $request->input('order_in');

        // user role is affiliate and load relationship affiliate
        $postbackLog = PostbackLog::query()

            ->when(!empty($request->search_txt), function ($query) use ($request) {

                $searchTxt = "%{$request->search_txt}%";

                return $query->where(function ($query) use ($searchTxt) {
                    $query->where('request_id', 'like', $searchTxt)
                        ->orWhere('type', 'like', $searchTxt);
                });

            })
            ->when(!empty($orderBy) && !empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                return $query->orderBy($orderBy, $orderIn);
            }, function ($query) {
                return $query->orderBy('id', 'desc');
            })
            ->latest('id')
            ->paginate($limit);

        return withSuccessResourceList(PostbackLogResource::collection($postbackLog));
    }

    /**
     * Retrieves a single Poatback Log based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, $id)
    {
        $postbackLog = PostbackLog::query()
            ->findOrFail($id);

        return withSuccess(new PostbackLogResource($postbackLog));
    }

}
