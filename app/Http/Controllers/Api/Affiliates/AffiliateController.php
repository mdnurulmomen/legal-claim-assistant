<?php

namespace App\Http\Controllers\Api\Affiliates;

use App\Http\Controllers\Api\Affiliates\Resources\AffiliateResource;
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
        $limit = $request->input('limit', 10);
        $orderBy = $request->input('order_by');
        $orderIn = $request->input('order_in');

        // user role is affiliate and load relationship affiliate
        $affiliates = User::query()
            ->where('role', 'affiliate')
            ->with('affiliate')
            ->withCount('postingDocs')
            ->when(!empty($request->search_txt), function ($query) use ($request) {
                return $query->whereAny([
                    'name',
                    'email',
                    'phone',
                    'company_name',
                ], 'like', '%' . $request->search_txt . '%');
            })
            ->when(!empty($orderBy) && !empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                return $query->orderBy($orderBy, $orderIn);
            }, function ($query) {
                return $query->orderBy('id', 'desc');
            })
            ->paginate($limit);

        Log::info('Affiliates retrieved successfully.', ['affiliates' => $affiliates]);

        return withSuccessResourceList(AffiliateResource::collection($affiliates));
    }

    /**
     * Retrieves a single affiliate based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function affiliate(Request $request, $id): Response
    {
        $affiliate = User::query()
            ->where('role', 'affiliate')
            ->with('affiliate')
            ->withCount('postingDocs')
            ->findOrFail($id);

        return withSuccessResource(new AffiliateResource($affiliate));
    }

    /**
     * Updates the affiliate based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id): Response
    {
        $affiliate = User::query()
            ->where('role', 'affiliate')
            ->findOrFail($id);

        $affiliate->update($request->all());

        return withSuccessResource(new AffiliateResource($affiliate));
    }
}
