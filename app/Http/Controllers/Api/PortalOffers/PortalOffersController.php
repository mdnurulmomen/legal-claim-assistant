<?php

namespace App\Http\Controllers\Api\PortalOffers;

use App\Http\Controllers\Api\PlatformList\Resources\PlatformListResource;
use App\Http\Controllers\Controller;
use App\Models\PortalOffers;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PortalOffers\Resources\PortalOffersResource;
use App\Http\Controllers\Api\PortalOffers\Requests\CreateOrUpdatePortalOffersRequest;
use App\Models\PlatformList;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PortalOffersController extends Controller
{
    public function index(Request $request)
    {
        $offers = PortalOffers::orderBy('created_at', 'desc');
        $offers = $offers->when(!empty($request->search_txt), function ($query) use ($request) {
            return $query->where('name', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $offers = $offers->paginate(10, ['*'], 'page', $request->page ?? 1);

        return withSuccessResourceList(PortalOffersResource::collection($offers));
    }

    public function show(Request $request, $tag)
    {
        $offer = PortalOffers::where('tag', $tag)->firstOrFail();
        return withSuccess(new PortalOffersResource($offer));
    }

    public function store(CreateOrUpdatePortalOffersRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('img')) {
            $file = $request->file('img');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $data['img'] = Storage::disk('s3')->putFileAs('upload/portal-offers', $file, $fileName);
            // image path amazon s3            
            $data['image_path'] = Storage::disk('s3')->url($data['img']);
        }

        // generate new twag uppercase 16
        $data['list_tag'] = $data['tag'];
        $data['tag'] = strtoupper(Str::random(16));
        $offer = PortalOffers::create($data);
        return withSuccess(new PortalOffersResource($offer), 'Portal Offer Created Successfully');
    }

    public function update(CreateOrUpdatePortalOffersRequest $request, $tag)
    {
        $offer = PortalOffers::where('tag', $tag)->firstOrFail();
        $data = $request->validated();

        if ($request->hasFile('img')) {
            // Delete old image if exists
            if ($offer->img) {
                Storage::disk('s3')->delete($offer->img);
            }

            $file = $request->file('img');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $data['img'] = Storage::disk('s3')->putFileAs('upload/portal-offers', $file, $fileName);
        }

        $offer->update($data);
        return withSuccess(new PortalOffersResource($offer), 'Portal Offer Updated Successfully');
    }

    public function delete(Request $request, $tag)
    {
        $offer = PortalOffers::where('tag', $tag)->firstOrFail();

        if ($offer->img) {
            Storage::disk('s3')->delete($offer->img);
        }

        $offer->delete();
        return withSuccess(message: 'Portal Offer Deleted Successfully');
    }

    public function lists()
    {
        $lists = PlatformList::query()
            ->where('status', 'Active')
            ->orderBy('created_at', 'desc')
            ->select('tag', 'name', 'id')
            ->get();

        return withSuccessResourceList(PlatformListResource::collection($lists));
    }

    public function criteria(Request $request, $tag)
    {
        // load the payout_mode from the request
        $payout_mode = $request->query('payout_mode');

        // log
        \Log::info('Payout Mode: ' . $payout_mode);

        $list = PlatformList::where('tag', $tag)->firstOrFail();        
        $integrations = $list->integrations;

        // Keys to ignore when combining filters
        $ignoredKeys = ['affid'];

        // Initialize arrays to store all filter values
        $combinedFilters = [];

        // only when integration is not empty
        if (!empty($integrations)) {
            // Loop through each integration
            foreach ($integrations as $integration) {
                // Skip if filter is not set or not an array
                if (!isset($integration['filter']) || !is_array($integration['filter'])) {
                    continue;
                }

                // Skip if integration is not active
                if (!isset($integration['active']) || $integration['active'] !== true) {
                    continue;
                }

                // Skip if payout_mode is specified and doesn't match the buyer_type
                if ($payout_mode !== null) {
                    $buyerType = $integration['buyer_type'] ?? null;
                    if (($payout_mode === 'CPL' && $buyerType !== 'CPL') ||
                        ($payout_mode === 'CPA' && $buyerType !== 'CPA')
                    ) {
                        continue;
                    }
                }

                // Loop through each filter in the integration
                foreach ($integration['filter'] as $key => $values) {
                    // Skip ignored keys
                    if (in_array($key, $ignoredKeys)) {
                        continue;
                    }

                    // Initialize the key if it doesn't exist
                    if (!isset($combinedFilters[$key])) {
                        $combinedFilters[$key] = [];
                    }

                    // Ensure values is an array
                    $values = (array) $values;

                    // Merge new values with existing ones
                    $combinedFilters[$key] = array_values(array_unique(
                        array_merge($combinedFilters[$key], $values)
                    ));
                }
            }
        }

        return withSuccess($combinedFilters);
    }
}
