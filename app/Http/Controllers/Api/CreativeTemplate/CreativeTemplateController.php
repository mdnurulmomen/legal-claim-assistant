<?php

namespace App\Http\Controllers\Api\CreativeTemplate;

use App\Helpers\Utility;
use App\Http\Controllers\Api\CreativeTemplate\Resources\CreativeTemplateResource;
use App\Http\Controllers\Controller;
use App\Models\CreativeTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CreativeTemplateController extends Controller
{
    /**
     * Retrieves a list of Creative Template based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $creatives = CreativeTemplate::orderBy('created_at', 'desc');
        $creatives = $creatives->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('name', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $creatives = $creatives->paginate(10, ['*'], 'page', $request->page ?? 1);

        return withSuccessResourceList(CreativeTemplateResource::collection($creatives));
    }

    /**
     * Retrieves a single Creative Template based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, $tag)
    {
        $creative = CreativeTemplate::where('tag', $tag)->first();
        return withSuccess(new CreativeTemplateResource($creative));
    }

    /**
     * create the Creative Template based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function store(Request $request)
    {
        $data = $request->only([
            'name',
            'template_offer_id',
        ]);

        if($request->hasFile('attachments')){
            $thumbPath = Storage::disk('s3')->put('upload/creative-template', $request->attachments);
            $data['attachments'] = $thumbPath;
        }
        $creative = CreativeTemplate::create($data);
        return withSuccess(new CreativeTemplateResource($creative), 'Creative Template Successfully');
    }

    /**
     * Updates the Creative Template based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $tag)
    {
        $creative = CreativeTemplate::where('tag', $tag)->first();

        $data = $request->only([
            'name',
            'template_offer_id',
        ]);

        if($request->hasFile('attachments')){
            if(!empty($creative->attachments)){
                Storage::disk('s3')->delete('upload/creative-template', $creative->attachments);
            }

            $thumbPath = Storage::disk('s3')->put('upload/creative-template', $request->attachments);
            $data['attachments'] = $thumbPath;
        }

        $creative->update($data);
        return withSuccess(new CreativeTemplateResource($creative), 'Creative Template Update Successfully');
    }

    /**
     * delete the Creative Template based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function delete(Request $request, $tag)
    {
        $creative = CreativeTemplate::where('tag', $tag)->first();

        $creative->delete();
        return withSuccess(message: 'Creative Template Deleted Successfully');
    }
}
