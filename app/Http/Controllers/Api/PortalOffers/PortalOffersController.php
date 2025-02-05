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
        }

        // generate new twag uppercase 16
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
            ->select('tag', 'name','id')
            ->get();

        return withSuccessResourceList(PlatformListResource::collection($lists));
    }

    public function criteria($tag)
    {
        $list = PlatformList::where('tag', $tag)->firstOrFail();
        $integrations = $list->integrations;
        
        // Initialize arrays to store all filter values
        $combinedFilters = [];
        
        // Loop through each integration
        foreach ($integrations as $integration) {
            if (!isset($integration['filter']) || !is_array($integration['filter'])) {
                continue;
            }
            
            // Loop through each filter in the integration
            foreach ($integration['filter'] as $key => $values) {
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
        
        return withSuccess($combinedFilters);
    }
}