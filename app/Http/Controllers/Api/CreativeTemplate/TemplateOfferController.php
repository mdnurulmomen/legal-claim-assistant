<?php

namespace App\Http\Controllers\Api\CreativeTemplate;

use App\Helpers\Utility;
use App\Http\Controllers\Api\CreativeTemplate\Resources\TemplateOfferResource;
use App\Http\Controllers\Api\CreativeTemplate\Resources\OfferResource;
use App\Http\Controllers\Controller;
use App\Models\TemplateOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class TemplateOfferController extends Controller
{
    /**
     * Retrieves a list of Template Offer based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $offers = TemplateOffer::orderBy('created_at', 'desc');
        $offers = $offers->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('name', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $offers = $offers->paginate(10, ['*'], 'page', $request->page ?? 1);

        return withSuccessResourceList(TemplateOfferResource::collection($offers));
    }

    /**
     * Retrieves a list of Template Offer based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function allOfferSelectFormat(Request $request)
    {
        $offers = TemplateOffer::orderBy('name', 'asc')->get();

        return withSuccessResourceList(OfferResource::collection($offers));
    }

    /**
     * Retrieves a single Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, $tag)
    {
        $offer = TemplateOffer::where('tag', $tag)->first();
        return withSuccess(new TemplateOfferResource($offer));
    }

    /**
     * create the Template Offer based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function store(Request $request)
    {
        $offer = TemplateOffer::create([
            'name'  =>  $request->name,
        ]);
        return withSuccess(new TemplateOfferResource($offer), 'Offer Created Successfully');
    }

    /**
     * Updates the Template Offer based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $tag)
    {
        $offer = TemplateOffer::where('tag', $tag)->first();

        $offer->update([
            'name'  =>  $request->name,
        ]);
        return withSuccess(new TemplateOfferResource($offer), 'Offer Update Successfully');
    }

    /**
     * delete a single Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function delete(Request $request, $tag)
    {
        $offer = TemplateOffer::where('tag', $tag)->firstOrFail();

        if (!$offer) {
            return withError('Invalid Conference ID');
        }
        $offer->delete();

        return withSuccess(message: 'Offer deleted successfully');
    }
}
