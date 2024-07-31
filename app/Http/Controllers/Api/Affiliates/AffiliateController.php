<?php

namespace App\Http\Controllers\Api\Affiliates;

use App\Http\Controllers\Api\Affiliates\Requests\CreateOrUpdateAffiliateRequest;
use App\Http\Controllers\Api\Affiliates\Resources\AffiliateResource;
use App\Http\Controllers\Api\Affiliates\Resources\SingleAffiliateResource;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AffiliateController extends Controller
{
    /**
     * Retrieves a list of affiliates based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function affiliates(Request $request)
    {
        $limit = $request->input('limit', 100);
        $orderBy = $request->input('order_by');
        $orderIn = $request->input('order_in');

        // user role is affiliate and load relationship affiliate
        $affiliates = User::query()
            ->where('role', 'affiliate')
            ->with('affiliate')
            ->withCount('postingDocs')
            ->when(!empty($request->search_txt), function ($query) use ($request) {
                return $query->whereAny(['name','email','username'], 'like', "%{$request->search_txt}%");
            })
            ->when(!empty($orderBy) && !empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                return $query->orderBy($orderBy, $orderIn);
            }, function ($query) {
                return $query->orderBy('id', 'desc');
            })
            ->latest('id')
            ->paginate($limit);

        return withSuccessResourceList(AffiliateResource::collection($affiliates));
    }

    /**
     * Retrieves a single affiliate based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function affiliate(Request $request, $id)
    {
        $affiliate = User::query()
            ->where('role', 'affiliate')
            ->with('affiliate')
            ->withCount('postingDocs')
            ->findOrFail($id);

        return withSuccess(new SingleAffiliateResource($affiliate));
    }

    /**
     * Updates the affiliate based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(CreateOrUpdateAffiliateRequest $request, $id)
    {
        $user = User::query()
            ->where('role', 'affiliate')
            ->withCount('postingDocs')
            ->findOrFail($id);

        $user->update($request->validated());

        // check if affiliate exists
        if (!$user->affiliate) {

            // create affiliate if not
            $user->affiliate()->create($request->only([
                'company_name',
                'country',
                'address',
                'zip',
            ]));
        } else {

            // update affiliate
            $user->affiliate()->update($request->only([
                'company_name',
                'country',
                'address',
                'zip',
            ]));
        }


        return withSuccess(new SingleAffiliateResource($user->load('affiliate')), 'Affiliate updated successfully');
    }

    /**
     * Store a new affiliate.
     *
     * @param Request $request
     * @return Response
     */
    public function create(CreateOrUpdateAffiliateRequest $request)
    {

        // create user with affiliate role and create aff with user_id and other data country, company_name, zip, address
        $affiliate = User::create($request->validated());
        $affiliate->affiliate()->create($request->only(
            [
                'company_name',
                'country',
                'address',
                'zip',]
        ));

        // return with success response
        return withSuccess(new AffiliateResource($affiliate->load('affiliate')), 'Affiliate created successfully');
    }

    /**
     * Activate or deactivate the affiliate based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function toggleStatus(Request $request, $id)
    {
        $affiliate = User::query()
            ->where('role', 'affiliate')
            ->findOrFail($id);

        $affiliate->update([
            'status' => !$affiliate->status
        ]);

        return withSuccess(new AffiliateResource($affiliate));
    }
}
