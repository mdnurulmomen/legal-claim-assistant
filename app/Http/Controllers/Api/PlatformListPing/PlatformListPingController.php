<?php

namespace App\Http\Controllers\Api\PlatformListPing;

use App\Http\Controllers\Api\PlatformListPing\Resources\PlatformListPingResource;
use App\Http\Controllers\Controller;
use App\Models\PlatformList;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;
use App\Models\PlatformDatas;
use App\Models\PlatformPings;
use App\Models\User;
use DataTables;
use DB;

class PlatformListPingController extends Controller
{

    /**
     * Retrieves a list of platform list pings based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function fetchData(Request $request): Response
    {
         $limit = $request->input('limit', 10);
        // $orderBy = $request->input('order_by') == 'total_leads' ? 'total' : $request->input('order_by');
        // $orderIn = $request->input('order_in');
        $start_date = false;
        $end_date   = false;
            
        if ($request->has('start_date') && $request->has('end_date') 
                && !empty($request->start_date) && !empty($request->end_date) ) {
            try {
                $start_date = Carbon::createFromFormat('Y-m-d H:i:s', $request->start_date);
                $end_date = Carbon::createFromFormat('Y-m-d H:i:s', $request->end_date);
            } catch (\Throwable $th) { 
                abort(404);
            }

        }     

        //init query
        $platformPingQuery = PlatformPings::query();
        $platformPingQuery = $platformPingQuery->leftJoin('users', 'users.id', '=', 'platform_pings.affiliate_id');
        $platformPingQuery = $platformPingQuery->leftJoin('platform_lists', 'platform_lists.id', '=', 'platform_pings.list_id');

        //filter dates
        if($start_date && $end_date){
            $platformPingQuery = $platformPingQuery->whereBetween('platform_pings.created_at', [$start_date, $end_date]);
        }

        //for seaerching
        if ($request->has('search_txt')) {
            
           $platformPingQuery = $platformPingQuery->where(function ($query) use ($request) { 
               //improved search lead algorithm
                if (substr($request->search_txt, 0, 2) === '+1' || is_numeric($request->search_txt) && strlen($request->search_txt) > 9 && strlen($request->search_txt) < 12) {
                    
                    try { 
                        $searchText = phone($request->search_txt, 'US')->formatE164();
                        $query->where('platform_pings.phone', '=', $searchText);
                        
                    } catch (\Throwable $th) {
                        //throw $th;
                    }

                } else {
                    
                    $query->orWhere('users.name', 'like', '%' . $request->search_txt . '%');
                    $query->orWhere('platform_lists.name', 'like', '%' . $request->search_txt . '%');
                    $query->orWhere('platform_pings.ping_id', 'like', '%' . $request->search_txt . '%');
                } 
            });
        }

        //additional filters
        if(!empty($request->list)) {
            $platformPingQuery = $platformPingQuery->whereIn('platform_pings.list_id', $request->list);
        }

        //affiliate filter
        if(!empty($request->affiliate)) {
            $platformPingQuery = $platformPingQuery->where(function ($query) use ($request) {
                foreach ($request->affiliate as $affiliateId) {
                    $query->where('users.id', $affiliateId);
                }
            });
        }

        //buyer filter
        if(!empty($request->buyer)) {
            $platformPingQuery = $platformPingQuery->where(function ($query) use ($request) {
                foreach ($request->buyer as $buyer) {
                    $query = $query->orWhere('platform_pings.buyer', $buyer);
                }
            });

        }

        //group by list and affid
        $pingLogData = $platformPingQuery->selectRaw('platform_pings.affiliate_id, users.name, platform_pings.list_id, platform_lists.name as list_name, platform_lists.tag as list_tag, platform_pings.phone, platform_pings.ping_id, platform_pings.buyer, platform_pings.internal_buyer_price, platform_pings.affiliate_price, platform_pings.sold, platform_pings.accepted, platform_pings.created_at')
                                ->paginate($limit);
                                
        return withSuccessResourceList(PlatformListPingResource::collection($pingLogData));
    }

    /**
     * Retrieves a list of sources based on the search text provided in the request.
     *
     * @param Request $request
     * @return Response
     */
    public function getFilterData(Request $request)
    {
        $filterBy = $request->filterBy;

        //init query
        $query = PlatformPings::query();

        if ($filterBy == 'list') {
            $query  = $query->leftJoin('platform_lists', 'platform_lists.id', '=', 'platform_pings.list_id');
            // $data = PlatformLists::query();

            $select_id = 'platform_lists.id as value';

            $select_text = 'CONCAT(platform_lists.name, " (", platform_lists.source, ") ") AS label';

            $search_elem = 'platform_lists.name';

            $data = $query->when(request('search'), function ($query) use($search_elem) {
                $search_query = filter_var(request('search'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                $query = $query->where(function($query_nested) use ($search_query, $search_elem) {
                    $query_nested->where($search_elem, 'LIKE', '%'.$search_query.'%');
                });

                return $query;
            })->groupBy('platform_pings.list_id')->select($select_id, DB::raw($select_text));

        } elseif($filterBy == 'buyer') {
            // $query  = $query->leftJoin('postback_conversions', 'postback_conversions.name', '=', 'platform_pings.buyer');

            $select_id      = 'buyer as value';
            $select_text    = 'buyer AS label';
            $search_elem    = 'buyer';

            $data = $query->when(request('search'), function ($query) use($search_elem) {
                $search_query = filter_var(request('search'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                $query = $query->where(function($query_nested) use ($search_query, $search_elem) {
                    $query_nested->where($search_elem, 'LIKE', '%'.$search_query.'%');
                });

                return $query;
            })->groupBy('buyer')->select($select_id, DB::raw($select_text));
            // ->orderByRaw('postback_conversions.id = 0 DESC');

        } elseif($filterBy == 'affiliate') {
            $query  = $query->leftJoin('users', 'users.id', '=', 'platform_pings.affiliate_id');

            $select_id      = 'users.id as value';
            $select_text    = 'users.name AS label';
            $search_elem    = 'users.name';

            $data = $query->when(request('search'), function ($query) use($search_elem) {
                $search_query = filter_var(request('search'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                $query = $query->where(function($query_nested) use ($search_query, $search_elem) {
                    $query_nested->where($search_elem, 'LIKE', '%'.$search_query.'%');
                });

                return $query;
            })->groupBy('platform_pings.affiliate_id')->union(
                User::query()->selectRaw('0 AS id, "Unknown" AS text')
            )->select($select_id, DB::raw($select_text));
        }


        // use simple pagination can improve performance
        // $data = $data->simplePaginate(10);
        $data = $data->get();

        $morePages = true;

        // check if there is more pages
        // if (empty($data->nextPageUrl())) {
        //    $morePages = false;
        // }

        // prepered response
        $results = array(
            "results" => $data,
            //"results" => $data->items(),
            //"pagination" => array(
            //    "more" => $morePages
            //)
        );
        return withSuccess($results);
        // return response()->json($results);
    }

}
