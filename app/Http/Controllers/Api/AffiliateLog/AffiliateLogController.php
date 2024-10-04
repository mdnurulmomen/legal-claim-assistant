<?php

namespace App\Http\Controllers\Api\AffiliateLog;

use App\Helpers\Utility; 
use App\Http\Controllers\Api\AffiliateLog\Resources\AffiliateLogResource; 
use App\Http\Controllers\Api\AffiliateLog\Resources\AffiliateUserResource;
use App\Http\Controllers\Controller;
use App\Models\AffiliateLog; 
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AffiliateLogController extends Controller
{
    /**
     * Retrieves a list of affiliate logs based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function affiliateLogs(Request $request)
    {
        $limit = $request->input('limit', 10);
        $orderBy = $request->input('order_by');
        $orderIn = $request->input('order_in');
        $status = array_search($request->input('status'), Utility::$userStatus);
            
        // get all affiliates
        $affiliates = AffiliateLog::with('user')
            ->when(!empty($request->search_txt), function ($query) use ($request) {

                $searchTxt = $request->search_txt;

                return $query->where(function ($query) use ($searchTxt, $request) {
                    $query->where('action', 'LIKE', '%' . $searchTxt . '%');
                    $query->orWhere('data', 'LIKE', '%' . $searchTxt . '%');
                    $query->orWhereHas('user', function($q) use($request) {
                        $q->where('name', 'LIKE', '%' . $request->name . '%');
                    });
                });

            })
            ->when(!empty($request->affiliate), function ($query) use ($request) {

                $affiliate = $request->affiliate;

                return $query->orWhereHas('user', function($q) use($request, $affiliate) {
                        $q->where('id', '=', $affiliate);
                    });

            })
            ->when(!empty($orderBy) && !empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                return $query->orderBy($orderBy, $orderIn);
            }, function ($query) {
                return $query->orderBy('id', 'desc');
            })
            ->latest('id')
            ->paginate($limit);

        return withSuccessResourceList(AffiliateLogResource::collection($affiliates));
    }
    
    /**
     * Retrieves a list of affiliate logs based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function affiliateUser(Request $request)
    {
        $users = User::where('role', 'affiliate')->get();

        return withSuccessResourceList(AffiliateUserResource::collection($users));
    }

}
