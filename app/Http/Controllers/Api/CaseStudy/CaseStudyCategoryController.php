<?php

namespace App\Http\Controllers\Api\CaseStudy;

use App\Helpers\Utility;
use App\Http\Controllers\Api\CaseStudy\Requests\CreateOrUpdateCaseStudyCategoryRequest;
use App\Http\Controllers\Api\CaseStudy\Resources\CaseStudyCategoryResource;
use App\Http\Controllers\Api\CaseStudy\Resources\CaseStudyCategoryForSelectResource;
use App\Http\Controllers\Controller;
use App\Models\CaseStudyCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CaseStudyCategoryController extends Controller
{
    /**
     * Retrieves a list of Case Study Categories based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);

        $categories = CaseStudyCategory::orderBy('created_at', 'desc');
        $categories = $categories->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('name', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $categories = $categories->paginate(10, ['*'], 'page', $request->page ?? 1);

        return withSuccessResourceList(CaseStudyCategoryResource::collection($categories));
    }

    /**
     * Retrieves a list of Case Study Categories by key, value.
     *
     * @param Request $request
     * @return Response
     */
    public function select(Request $request)
    {

        $categories = CaseStudyCategory::orderBy('created_at', 'desc')
                    ->when(! empty($request->ignoreCategory), function ($query) use ($request) {
                        $query->where('id', '!=', (int)$request->ignoreCategory);
                    });

        //paginate
        $categories = $categories->get();

        return withSuccessResourceList(CaseStudyCategoryForSelectResource::collection($categories));
    }

    /**
     * Store a new Case Study Category.
     *
     * @param Request $request
     * @return Response
     */
    public function create(CreateOrUpdateCaseStudyCategoryRequest $request)
    {
        $data = $request->only([
            'parent_id',
            'name',
        ]);

        $category = CaseStudyCategory::create($data);

        // return with success response
        return withSuccess(new CaseStudyCategoryResource($category), 'Case Study created successfully');
    }

    /**
     * Retrieves a single Case Study Category based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, String $tag)
    {
        $category = CaseStudyCategory::where('tag', $tag)->firstOrFail();

        if (!$category) {
            return withError('Invalid Category Tag');
        }

        return withSuccess(new CaseStudyCategoryResource($category));
    }

    /**
     * Updates the Case Study Category based on the provided ID.
     *
     * @param Request $request
     * @param String $tag
     * @return Response
     */
    public function update(CreateOrUpdateCaseStudyCategoryRequest $request, String $tag)
    {
        $category = CaseStudyCategory::where('tag', $tag)->firstOrFail();

        if (!$category) {
            return withError('Invalid Category Tag');
        }
        $data = $request->only([
            'parent_id',
            'name',
        ]);

        $category->update($data);


        return withSuccess(new CaseStudyCategoryResource($category), 'Category updated successfully');
    }

    /**
     * delete a single Case Study Category based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function delete(Request $request, $tag)
    {
        $category = CaseStudyCategory::where('tag', $tag)->firstOrFail();

        if (!$category) {
            return withError('Invalid Category Tag');
        }
        $category->delete();

        return withSuccess(message: 'Category deleted successfully');
    }
}
