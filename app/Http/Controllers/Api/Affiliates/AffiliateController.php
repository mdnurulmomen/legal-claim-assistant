<?php

namespace App\Http\Controllers\Api\Affiliates;

use App\Helpers\Utility;
use App\Http\Controllers\Api\Affiliates\Requests\CreateOrUpdateAffiliateRequest;
use App\Http\Controllers\Api\Affiliates\Resources\AffiliateResource;
use App\Http\Controllers\Api\Affiliates\Resources\SingleAffiliateResource;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AccountManager;
use App\Models\Impersonation;
use App\Models\User;
use App\Services\Lead\LeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

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
        $status = array_search($request->input('status'), Utility::$userStatus);

        // user role is affiliate and load relationship affiliate
        $affiliates = User::query()
            ->where('role', 'affiliate')
            ->with('affiliate')
            ->withCount('postingDocs')

            ->when(!empty($request->search_txt), function ($query) use ($request) {

                $searchTxt = "%{$request->search_txt}%";

                return $query->where(function ($query) use ($searchTxt, $request) {

                    $affIds = explode(',', str_replace(' ', '', $request->search_txt));

                    return $query->where(function($query) use ($request, $affIds) {
                        return $query->where('data->affids', 'like', '%' . $request->search_txt . '%')
                                    ->orWhereJsonContains('data->affids', $affIds);
                    })
                    ->when(hasAffiliateAccess(), function ($query) use ($request, $searchTxt) {
                        return $query->orWhere('name', 'like', $searchTxt)
                                    ->orWhere('email', 'like', $searchTxt)
                                    ->orWhere('username', 'like', $searchTxt)
                                    ->orWhereHas('affiliate', function ($query) use ($searchTxt) {
                                            $query->where('country', 'like', $searchTxt)
                                            ->orWhere('company_name', 'like', $searchTxt);
                                    });

                    });
                });

            })
            ->when($status !== false, function ($query) use ($status) {
                return $query->where('status', $status);
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

        //additional validation on affid
        //find users where they have the same affid on data->affids
        $existingAffids = User::query()
            ->where('role', 'affiliate')
            ->where('id', '!=', $id)
            ->where(function ($query) use ($request) {
                foreach ($request->affids as $affid) {
                    $query->orWhereJsonContains('data->affids', $affid);
                }
            })
            ->first();

        if ($existingAffids) {
            return withError('Affiliate IDs are already assigned to another affiliate');
        }

        //get the validated data
        $validatedData = $request->validated();

        //get current data
        $userData = $user->data;

        //unset the old affids
        unset($userData['affids'], $userData['reportColumnsPPL']);

        //set the new affids
        $userData['affids'] = $request->affids;
        if(! empty($request->report_columns)){
            $userData['reportColumnsPPL'] = $request->report_columns;
        }

        //manipulate the request data and assign the new data
        $validatedData = array_merge($validatedData, ['data' => $userData]);

        //unset the affids from the validated data
        unset($validatedData['affids']);

        // unset password is empty
        if( empty(trim($validatedData['password'])) || empty(trim($validatedData['password_confirmation'])) ){
            unset($validatedData['password']);
            unset($validatedData['password_confirmation']);
        }

        $user->update($validatedData);

        // check if affiliate exists
        if (!$user->affiliate) {

            // create affiliate if not
            $user->affiliate()->create($request->only([
                'company_name',
                'country',
                'address',
                'zip',
                'bank_name',
                'bank_account_name',
                'bank_account_number',
                'bank_swift_code',
                'vat_number',
            ]));
        } else {

            // update affiliate
            $user->affiliate()->update($request->only([
                'company_name',
                'country',
                'address',
                'zip',
                'bank_name',
                'bank_account_name',
                'bank_account_number',
                'bank_swift_code',
                'vat_number',
            ]));
        }

        if( isset($validatedData['manager']) && !empty($validatedData['manager']) ){
            AccountManager::updateOrCreate(
                [ 'affiliate_id'    => $user->id ],
                [ 'user_id'         =>  $validatedData['manager'] ]
            );
        } else {
            AccountManager::where('affiliate_id', $user->id)->delete();
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

        //additional validation on affid
        //find users where they have the same affid on data->affids
        $existingAffids = User::query()
            ->where('role', 'affiliate')
            ->where(function ($query) use ($request) {
                foreach ($request->affids as $affid) {
                    $query->orWhereJsonContains('data->affids', $affid);
                }
            })
            ->first();

        if ($existingAffids) {
            return withError('Affiliate IDs are already assigned to another affiliate');
        }

        $validatedData = $request->validated();

        $data = ['affids' => $validatedData['affids']];
        if(! empty($request->report_columns)){
            $data['reportColumnsPPL'] = $request->report_columns;
        }

        $validatedData['data'] = $data;

        unset($validatedData['report_columns'], $validatedData['affids']);

        $affiliate = User::create($validatedData);
        $affiliate->affiliate()->create($request->only(
            [
                'company_name',
                'country',
                'address',
                'zip',
                'bank_name',
                'bank_account_name',
                'bank_account_number',
                'bank_swift_code',
                'vat_number',
            ]
        ));

        if( isset($validatedData['manager']) && !empty($validatedData['manager']) ){
            AccountManager::updateOrCreate(
                [ 'affiliate_id'    => $affiliate->id ],
                [ 'user_id'         =>  $validatedData['manager'] ]
            );
        }

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

    /**
     * Retrieves a list of affiliates based on the request parameters.
     *
     * @param Request $request
     * @param LeadService $leadService
     * @return Response
     */
    public function affiliateList(Request $request, LeadService $leadService): Response
    {
        $affiliates = $leadService->getAffiliates($request);
        return withSuccess($affiliates,  'Affiliates retrieved successfully');
    }

    /**
     * Save the report columns for the affiliate based on the provided ID.
     *
     * @param Request $request
     * @param int $userId
     * @return Response
     */
    public function saveReportColumns(Request $request, int $userId): Response
    {
        $validator = Validator::make($request->all(), [
            'report_columns' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return withError($validator->errors()->first());
        }

        $user = User::find($userId);
        if(empty($user)){
            return withError('Affiliate not found!');
        }

        $data = $user->data ?? [];
        $data['reportColumnsPPL'] = $request->report_columns;

        if(empty($data['reportColumnsPPL'])){
            unset($data['reportColumnsPPL']);
        }

        $user->data = $data;
        $user->save();

        return withSuccess(message: 'Report columns saved successfully');
    }

    /**
     * Impersonation of the affiliate based on the provided ID.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function impersonate(Request $request, $id)
    {
        //check if the user is an affiliate
        $affiliate = User::query()
            ->where('role', 'affiliate')
            ->findOrFail($id);

        //create a new impersonation record
        $impersonation = new Impersonation();
        $impersonation->accessKey = Str::random(60);
        $impersonation->impersonator_id = auth()->user()->id;
        $impersonation->user_id = $id;
        $impersonation->save();

        //redirect to the impersonation url
        $url = "https://legalclaimassistant.support/auth/impersonate?accessKey=" . $impersonation->accessKey;

        return withSuccess(['url' => $url], 'Impersonation started successfully');
    }
}
