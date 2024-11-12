<?php

namespace App\Http\Controllers\Api\PlatformList;

use App\Http\Controllers\Api\PlatformList\Requests\SpecsSettingRequest;
use App\Http\Controllers\Api\PlatformList\Resources\PlatformSpecsResource;
use App\Http\Controllers\Controller;
use App\Models\PartnerPlatformConnection;
use App\Models\User;
use App\Services\Platform\PlatformSpecsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PlatformSpecsController extends Controller
{
    /**
     * List of all specs belongs to given platform.
     *
     * @param Request $request
     * @param int $platformId
     * @return Response
     */
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
                        'users.email',
                        'users.api_token',
                        'users.id as affiliate_id',
                        'affiliate.name as affiliate_name',
                        'affiliate.id as affiliate_master_id',
                        'affiliate.data->affids as affids',
                        'pl.tag as platform_tag',
                        'partner_platform_connections.created_at',
                        'partner_platform_connections.is_active',
                        'partner_platform_connections.options->posting_type as label',
                        'partner_platform_connections.options->force_pingpost_sell as force_pingpost_sell',
                        'partner_platform_connections.options->affid as affid',
                        'partner_platform_connections.options->internal_affiliate as internal_affiliate',
                    )
                    ->selectRaw("
                        JSON_LENGTH(partner_platform_connections.options->'$.lead_posting.buyers') as buyers_count,
                        JSON_EXTRACT(partner_platform_connections.options, '$.lead_posting.buyers') as buyers,
                        JSON_EXTRACT(partner_platform_connections.options, '$.lead_posting.payout') as payout,
                        JSON_EXTRACT(partner_platform_connections.options, '$.lead_posting.optional_fields') as optional_fields,
                        JSON_EXTRACT(partner_platform_connections.options, '$.lead_posting.required_fields') as required_fields,
                        JSON_EXTRACT(partner_platform_connections.options, '$.lead_posting.ping_required_fields') as ping_required_fields
                    ")
                    ->when(! empty($request->platform_id), function($query) use ($request) {
                        return $query->where('affiliate.id',  $request->platform_id);
                    })
                    ->when(! empty($request->search_txt), function ($query) use ($searchTxt) {

                        return $query->where(function($query) use ($searchTxt) {

                            $formattedTxt = explode(',', str_replace(' ', '', "%{$searchTxt}%"));

                            return $query->where('users.name', 'like', "%{$searchTxt}%")
                                ->when( hasAffiliateAccess(), function ($query) use ($searchTxt) {
                                    return $query->orWhere('affiliate.name', 'like', "%{$searchTxt}%");
                                })
                                ->orWhere('affiliate.data->affids', 'like', "%{$searchTxt}%")
                                ->orWhereJsonContains('affiliate.data->affids', $formattedTxt);
                        });
                    })
                    ->latest('id')
                    ->paginate($limit);

        return withSuccessResourceList(PlatformSpecsResource::collection($specs));
    }

    /**
     * Delete a specs for a given platform
     *
     * @param Request $request
     * @param int $specsId
     * @return Response
     */
    public function deleteSpecs(Request $request, int $specsId): Response
    {
        $specs = PartnerPlatformConnection::find($specsId);
        if(empty($specs)) {
            return withError('Specs not found');
        }

        $specs->delete();
        return withSuccess(message: 'Specs deleted successfully');
    }

    /**
     * Save a specs for a given platform
     *
     * @param SpecsSettingRequest $request
     * @param int $specsId
     * @param PlatformSpecsService $specsService
     * @return Response
     */
    public function saveSpecs(SpecsSettingRequest $request, int $specsId, PlatformSpecsService $specsService): Response
    {
        $specs = PartnerPlatformConnection::find($specsId);
        if(empty($specs)) {
            return withError('Specs not found');
        }

        $affiliate = User::find($specs->user_id);
        if(empty($affiliate)) {
            return withError('Affiliate not found');
        }

        DB::beginTransaction();

        try {

            $specsService->saveAffiliate($request, $affiliate);
            $specsService->saveSpecs($request, $specs);

            DB::commit();
            return withSuccess(message: 'Specs saved successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            info($th->getMessage());
            return withError('Specs Saved Failed!');
        }
    }

    /**
     * Store a new specs for a given platform
     *
     * @param SpecsSettingRequest $request
     * @param PlatformSpecsService $specsService
     * @return Response
     */
    public function storeSpecs(SpecsSettingRequest $request, PlatformSpecsService $specsService): Response
    {
        DB::beginTransaction();

        try {
            $user = $specsService->createAffiliateUser($request);
            $specsService->createSpecs($request, $user);

            DB::commit();
            return withSuccess(message: 'Specs saved successfully');
        } catch (\Throwable $th) {
            info($th->getMessage());
            DB::rollBack();
            return withError('Specs Saved Failed!');
        }
    }

    /**
     * Update the status of specs for a given platform
     *
     * @param Request $request
     * @param int $specsId
     * @return Response
     */
    public function updateStatus(Request $request, int $specsId): Response
    {
        $specs = PartnerPlatformConnection::find($specsId);
        if(empty($specs)) {
            return withError('Specs not found');
        }

        $specs->is_active = $specs->is_active ? 0 : 1;
        $specs->save();
        return withSuccess(message: 'Specs status updated successfully');
    }
}
