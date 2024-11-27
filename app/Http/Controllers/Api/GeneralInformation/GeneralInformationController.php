<?php

namespace App\Http\Controllers\Api\GeneralInformation;

use App\Helpers\Utility;
use App\Http\Controllers\Api\GeneralInformation\Requests\CreateOrUpdateGeneralInformationRequest;
use App\Http\Controllers\Api\GeneralInformation\Resources\GeneralInformationResource;
use App\Http\Controllers\Controller;
use App\Models\GeneralInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class GeneralInformationController extends Controller
{
    /**
     * Retrieves a list of General Information Event based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);

        $conferences = GeneralInformation::orderBy('created_at', 'desc');
        $conferences = $conferences->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('title', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $conferences = $conferences->paginate(10, ['*'], 'page', $request->page ?? 1);

        // paginate($limit);

        return withSuccessResourceList(GeneralInformationResource::collection($conferences));
    }

    /**
     * Store a new General Information.
     *
     * @param Request $request
     * @return Response
     */
    public function create(CreateOrUpdateGeneralInformationRequest $request)
    {
        $data = $request->only([
            'description',
        ]);

        $conference = GeneralInformation::create($data);

        // return with success response
        return withSuccess(new GeneralInformationResource($conference), 'General Information created successfully');
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
        $conference = GeneralInformation::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid General Information ID');
        }

        return withSuccess(new GeneralInformationResource($conference));
    }

     /**
     * Retrieves a single Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function showFirstOne(Request $request, )
    {
        $conference = GeneralInformation::firstOrFail();

        if (!$conference) {
            return withError('Invalid General Information ID');
        }

        return withSuccess(new GeneralInformationResource($conference));
    }

    /**
     * Updates the Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(CreateOrUpdateGeneralInformationRequest $request, $tag)
    {
        $conference = GeneralInformation::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid General Information ID');
        }
        $data = $request->only([
            'description',
        ]);

        $conference->update($data);


        return withSuccess(new GeneralInformationResource($conference), 'General Information updated successfully');
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
        $conference = GeneralInformation::where('tag', $tag)->firstOrFail();

        if (!$conference) {
            return withError('Invalid General Information ID');
        }
        $conference->delete();

        return withSuccess(message: 'General Information deleted successfully');
    }
}
