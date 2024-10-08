<?php

namespace App\Http\Controllers\Api\PlatformList;

use App\Http\Controllers\Api\PlatformList\Resources\PlatformSpecsResource;
use App\Http\Controllers\Controller;
use App\Models\PartnerPlatformConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlatformSpecsController extends Controller
{
    public function specsList(Request $request, int $platformId): Response
    {
        $limit = empty($request->limit) ? 10 : $request->limit;
        $searchTxt = $request->search_txt;

        $specs = PartnerPlatformConnection::query()
                    ->leftJoin('users', 'partner_platform_connections.user_id', '=', 'users.id')
                    ->leftJoin('users as affiliate', 'users.master_user_id', '=', 'affiliate.id')
                    ->leftJoin('platform_lists as pl', 'partner_platform_connections.platform_id', '=', 'pl.id')
                    ->where('partner_platform_connections.platform_id', $platformId)
                    ->select(
                        'partner_platform_connections.id',
                        'users.name',
                        'users.api_token',
                        'affiliate.name as affiliate_name',
                        'affiliate.data->affids as affids',
                        'partner_platform_connections.options->posting_type as label',
                        'pl.tag as platform_tag',
                        'partner_platform_connections.created_at',
                        'partner_platform_connections.is_active'
                    )
                    ->selectRaw("
                        JSON_LENGTH(partner_platform_connections.options->'$.lead_posting.buyers') as buyers_count,
                        JSON_EXTRACT(partner_platform_connections.options, '$.lead_posting.buyers') as buyers,
                        JSON_EXTRACT(partner_platform_connections.options, '$.lead_posting.payout') as payout
                    ")
                    ->when(! empty($request->search_txt), function ($query) use ($searchTxt) {

                        return $query->where(function($query) use ($searchTxt) {
                            return $query->where('users.name', 'like', "%{$searchTxt}%")
                                ->when(! hasAffiliateAccess(), function ($query) use ($searchTxt) {
                                    $formattedTxt = explode(',', str_replace(' ', '', "%{$searchTxt}%"));

                                    return $query->orWhere('affiliate.data->affids', 'like', "%{$searchTxt}%")
                                        ->orWhereJsonContains('affiliate.data->affids', $formattedTxt);
                                }, function ($query) use ($searchTxt) {
                                    return $query->orWhere('affiliate.name', 'like', "%{$searchTxt}%");
                                });
                        });
                    })
                    ->paginate($limit);

        return withSuccessResourceList(PlatformSpecsResource::collection($specs));
    }

    public function deleteSpecs(Request $request, int $specsId): Response
    {
        $specs = PartnerPlatformConnection::find($specsId);
        if(empty($specs)) {
            return withError('Specs not found');
        }

        $specs->delete();
        return withSuccess(message: 'Specs deleted successfully');
    }
}
