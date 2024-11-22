<?php

namespace App\Http\Controllers\Api\NewestCampaign;

use App\Helpers\Utility;
use App\Http\Controllers\Api\NewestCampaign\Requests\CreateOrUpdateNewestCampaignRequest;
use App\Http\Controllers\Api\NewestCampaign\Resources\NewestCampaignResource;
use App\Http\Controllers\Controller;
use App\Models\NewestCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class NewestCampaignController extends Controller
{
    /**
     * Retrieves a list of Conference Event based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);

        $conferences = NewestCampaign::orderBy('created_at', 'desc');
        $conferences = $conferences->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('title', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $conferences = $conferences->paginate(10, ['*'], 'page', $request->page ?? 1);

        // paginate($limit);

        return withSuccessResourceList(NewestCampaignResource::collection($conferences));
    }

    /**
     * Store a new Conference Event.
     *
     * @param Request $request
     * @return Response
     */
    public function create(CreateOrUpdateNewestCampaignRequest $request)
    {
        $data = $request->only([
            'id',
            'title',
            'description',
        ]);

        if($request->hasFile('thumb')){
            $thumbPath = Storage::disk('s3')->put('upload/newest-campaign', $request->thumb);
            $data['thumb'] = $thumbPath;
        }

        // strtoupper(Str::random(15));

        $conference = NewestCampaign::create($data);

        // return with success response
        return withSuccess(new NewestCampaignResource($conference), 'Campaign created successfully');
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
        $conference = NewestCampaign::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid Conference ID');
        }

        return withSuccess(new NewestCampaignResource($conference));
    }

    /**
     * Updates the Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(CreateOrUpdateNewestCampaignRequest $request, $tag)
    {
        $conference = NewestCampaign::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid Conference ID');
        }
        $data = $request->only([
            'title',
            'description',
        ]);

        if($request->hasFile('thumb')){
            $thumbPath = Storage::disk('s3')->put('upload/newest-campaign', $request->thumb);
            $thumb = $thumbPath;
            $data['thumb'] = $thumb;
        }

        $conference->update($data);


        return withSuccess(new NewestCampaignResource($conference), 'Campaign updated successfully');
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
        $conference = NewestCampaign::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid Conference ID');
        }
        $conference->delete();

        return withSuccess(message: 'Campaign deleted successfully');
    }
}
