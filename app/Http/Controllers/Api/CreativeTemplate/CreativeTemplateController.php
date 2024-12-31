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
            'description',
            'template_offer_id',
        ]);

        if( $request->hasFile('attachments') ){
            $attachments = [];
            $files = $request->file('attachments');

            // dd($files);
            foreach( $files as $file ){
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();

                // Generate a filename with timestamp, replace spaces with underscores
                $timestamp = time();
                $sanitizedFileName = preg_replace('/\s+/', '_', $originalName); // Replace spaces with underscores
                $uniqueName = "{$sanitizedFileName}_{$timestamp}.{$extension}";


                $thumbPath = Storage::disk('s3')->putFileAs('upload/creative-template', $file, $uniqueName);
                $attachments[] = $thumbPath;
                // $attachments[] = Storage::disk('s3')->url($thumbPath);
            }

            $data['attachments'] = $attachments;
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
            'description',
        ]);

        if( $request->has('attachments_paths') ){
            foreach( $creative->attachments as $oldFile ){
                if(in_array($oldFile, $request->attachments_paths) ){
                    $data['attachments'][] = $oldFile;
                } else {
                    Storage::disk('s3')->delete('upload/creative-template', $oldFile);
                }
            }
        }

        if( $request->hasFile('attachments') ){
            $files = $request->file('attachments');

            foreach( $files as $file ){

                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();

                // Generate a filename with timestamp, replace spaces with underscores
                $timestamp = time();
                $sanitizedFileName = preg_replace('/\s+/', '_', $originalName); // Replace spaces with underscores
                $uniqueName = "{$sanitizedFileName}_{$timestamp}.{$extension}";


                $thumbPath = Storage::disk('s3')->putFileAs('upload/creative-template', $file, $uniqueName);
                $data['attachments'][] = $thumbPath;
                // $attachments[] = Storage::disk('s3')->url($thumbPath);
            }
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

        if( count($creative->attachments) > 0 ){
            foreach( $creative->attachments as $file ){
                if(!empty($file) ){
                    Storage::disk('s3')->delete('upload/creative-template', $file);
                }
            }
        }

        $creative->delete();
        return withSuccess(message: 'Creative Template Deleted Successfully');
    }
}
