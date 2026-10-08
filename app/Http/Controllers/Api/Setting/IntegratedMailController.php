<?php

namespace App\Http\Controllers\Api\Setting;

use App\Http\Controllers\Api\Setting\Requests\CreateOrUpdateIntegratedMailRequest;
use App\Http\Controllers\Api\Setting\Resources\IntegratedMailResource;
use App\Http\Controllers\Controller;
use App\Models\IntegrationEmail;
use App\Services\IntegratedMailService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IntegratedMailController extends Controller
{
    /**
     * Returns a list of integration mail contents.
     *
     * @param Request $request
     * @return Response
     */
    public function contentList(Request $request): Response
    {
        $limit = $request->input('limit', 10);

        $contents = IntegrationEmail::query()
                        ->select('id', 'title', 'status', 'created_at')
                        ->when(! empty($request->search_txt), function ($query) use ($request) {
                            $searchTxt = "%{$request->search_txt}%";
                            return $query->where('title', 'like', $searchTxt);
                        })
                        ->latest('id')
                        ->paginate($limit);

        return withSuccessResourceList(IntegratedMailResource::collection($contents));
    }

    /**
     * Retrieves a list of integrated mail contents to be used in the dropdown list.
     *
     * @param Request $request
     * @return Response
     */
    public function dropdownList(Request $request): Response
    {
        $limit = $request->input('per_page', 20);

        $contents = IntegrationEmail::query()
                        ->select('id as value', 'title as label')
                        ->when(! empty($request->search), function ($query) use ($request) {
                            return $query->where('title', 'like', "%{$request->search}%");
                        })
                        ->where('status', 'active')
                        ->paginate($limit);

        return withSuccess($contents);
    }

    /**
     * Saves the content of integration mail
     *
     * @param CreateOrUpdateIntegratedMailRequest $request
     * @param IntegratedMailService $service
     * @return Response
     */
    public function saveContent(CreateOrUpdateIntegratedMailRequest $request, IntegratedMailService $service): Response
    {
        IntegrationEmail::create($request->validated());
        return withSuccess(message: 'Integration Mail Created Successfully!');
    }

    /**
     * Displays the content of a specific integration mail.
     *
     * @param Request $request
     * @param int $contentId
     * @return Response
     */
    public function showContent(Request $request, int $contentId): Response
    {
        $content = IntegrationEmail::select('id', 'title', 'status', 'content', 'created_at')->find($contentId);
        if(empty($content)) {
            return withError(message: 'Integration Mail not found!');
        }
        return withSuccess(new IntegratedMailResource($content));
    }

    /**
     * Updates the content of integration mail.
     *
     * @param CreateOrUpdateIntegratedMailRequest $request
     * @param int $contentId
     * @param IntegratedMailService $service
     * @return Response
     */
    public function updateContent(CreateOrUpdateIntegratedMailRequest $request, int $contentId, IntegratedMailService $service): Response
    {
        $content = IntegrationEmail::find($contentId);
        if(empty($content)) {
            return withError('Integration Mail not found');
        }

        $content->title = $request->title;
        $content->content = $request->content;
        $content->status = $request->status;
        $content->save();

        return withSuccess(message: 'Integration Mail Updated Successfully!');
    }

    /**
     * Deletes the content of integration mail.
     *
     * @param Request $request
     * @param int $contentId
     * @return Response
     */
    public function deleteContent(Request $request, int $contentId): Response
    {
        $content = IntegrationEmail::find($contentId);
        if(empty($content)) {
            return withError('Integration Mail not found');
        }

        $content->delete();

        return withSuccess(message: 'Integration Mail Deleted Successfully!');
    }
}
