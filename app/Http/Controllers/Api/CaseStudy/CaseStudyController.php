<?php

namespace App\Http\Controllers\Api\CaseStudy;

use App\Helpers\Utility;
use App\Http\Controllers\Api\CaseStudy\Requests\CreateOrUpdateCaseStudyRequest;
use App\Http\Controllers\Api\CaseStudy\Resources\CaseStudyResource;
use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CaseStudyController extends Controller
{
    /**
     * Retrieves a list of Case Study based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);

        $conferences = CaseStudy::orderBy('created_at', 'desc');
        $conferences = $conferences->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('title', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $conferences = $conferences->paginate(10, ['*'], 'page', $request->page ?? 1);

        return withSuccessResourceList(CaseStudyResource::collection($conferences));
    }

    /**
     * Store a new Case Study.
     *
     * @param Request $request
     * @return Response
     */
    public function create(CreateOrUpdateCaseStudyRequest $request)
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

        $conference = CaseStudy::create($data);

        // return with success response
        return withSuccess(new CaseStudyResource($conference), 'Case Study created successfully');
    }

    /**
     * Retrieves a single Case Study based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, String $tag)
    {
        $conference = CaseStudy::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid Case Study Tag');
        }

        return withSuccess(new CaseStudyResource($conference));
    }

    /**
     * Updates the Case Study based on the provided ID.
     *
     * @param Request $request
     * @param String $tag
     * @return Response
     */
    public function update(CreateOrUpdateCaseStudyRequest $request, String $tag)
    {
        $conference = CaseStudy::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid Case Study Tag');
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


        return withSuccess(new CaseStudyResource($conference), 'Case Study updated successfully');
    }

    /**
     * delete a single Case Study based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function delete(Request $request, $tag)
    {
        $conference = CaseStudy::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid Case Study Tag');
        }
        $conference->delete();

        return withSuccess(message: 'Case Study deleted successfully');
    }
}
