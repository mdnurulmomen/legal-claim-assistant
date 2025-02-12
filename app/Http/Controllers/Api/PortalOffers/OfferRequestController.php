<?php

namespace App\Http\Controllers\Api\PortalOffers;

use App\Http\Controllers\Api\PortalOffers\Resources\OfferRequestResource;
use App\Http\Controllers\Controller;
use App\Models\OfferRequest;
use Illuminate\Http\Request;

class OfferRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = OfferRequest::with(['offer', 'affiliate'])
            ->orderBy('created_at', 'desc');

        $requests = $requests->when(!empty($request->search_txt), function ($query) use ($request) {
            return $query->whereHas('offer', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search_txt . '%');
            });
        });

        //paginate
        $requests = $requests->paginate(10, ['*'], 'page', $request->page ?? 1);

        return withSuccessResourceList(OfferRequestResource::collection($requests));
    }

    public function approve(Request $request, $id)
    {
        $offerRequest = OfferRequest::findOrFail($id);
        $offerRequest->update([
            'status' => 'Approved',
            'action_notes' => $request->action_notes
        ]);

        return withSuccess(new OfferRequestResource($offerRequest), 'Request Approved Successfully');
    }

    public function disapprove(Request $request, $id)
    {
        $offerRequest = OfferRequest::findOrFail($id);
        $offerRequest->update([
            'status' => 'Disapproved',
            'action_notes' => $request->action_notes
        ]);

        return withSuccess(new OfferRequestResource($offerRequest), 'Request Disapproved Successfully');
    }

    public function delete($id)
    {
        $offerRequest = OfferRequest::findOrFail($id);
        $offerRequest->delete();

        return withSuccess(message: 'Request Deleted Successfully');
    }
} 