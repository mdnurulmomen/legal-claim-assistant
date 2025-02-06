<?php

namespace App\Http\Controllers\Api\CaseStudy;

use App\Helpers\Utility;
use App\Http\Controllers\Api\CaseStudy\Requests\CreateOrUpdateCaseStudyTagRequest;
use App\Http\Controllers\Api\CaseStudy\Resources\CaseStudyTagResource;
use App\Http\Controllers\Api\CaseStudy\Resources\CaseStudyTagForSelectResource;
use App\Http\Controllers\Controller;
use App\Models\CaseStudyTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CaseStudyTagController extends Controller
{
    /**
     * Retrieves a list of Case Study Tag based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);

        $conferences = CaseStudyTag::orderBy('created_at', 'desc');
        $conferences = $conferences->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('name', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $conferences = $conferences->paginate(10);

        return withSuccessResourceList(CaseStudyTagResource::collection($conferences));
    }

    /**
     * Retrieves a list of Case Study Categories by key, value.
     *
     * @param Request $request
     * @return Response
     */
    public function select(Request $request)
    {

        $categories = CaseStudyTag::orderBy('created_at', 'desc')
                    ->when(! empty($request->ignoreCategory), function ($query) use ($request) {
                        $query->where('id', '!=', (int)$request->ignoreCategory);
                    });

        //paginate
        $categories = $categories->get();

        return withSuccessResourceList(CaseStudyTagForSelectResource::collection($categories));
    }

    /**
     * Store a new Case Study Tag.
     *
     * @param Request $request
     * @return Response
     */
    public function create(CreateOrUpdateCaseStudyTagRequest $request)
    {
        $data = $request->only([
            'name',
        ]);

        $conference = CaseStudyTag::create($data);

        // return with success response
        return withSuccess(new CaseStudyTagResource($conference), 'Tag created successfully');
    }

    /**
     * Retrieves a single Case Study Tag based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, String $tag)
    {
        $conference = CaseStudyTag::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid Tag');
        }

        return withSuccess(new CaseStudyTagResource($conference));
    }

    /**
     * Updates the Case Study Tag based on the provided ID.
     *
     * @param Request $request
     * @param String $tag
     * @return Response
     */
    public function update(CreateOrUpdateCaseStudyTagRequest $request, String $tag)
    {
        $conference = CaseStudyTag::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid Case Study Tag');
        }
        $data = $request->only([
            'name',
        ]);

        $conference->update($data);


        return withSuccess(new CaseStudyTagResource($conference), 'Tag updated successfully');
    }

    /**
     * delete a single Case Study Tag based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function delete(Request $request, $tag)
    {
        $conference = CaseStudyTag::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid Tag');
        }
        $conference->delete();

        return withSuccess(message: 'Tag deleted successfully');
    }
}
