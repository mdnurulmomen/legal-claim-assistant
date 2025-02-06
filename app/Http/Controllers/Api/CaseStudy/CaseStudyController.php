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
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Http\File;

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

        $posts = CaseStudy::orderBy('created_at', 'desc');
        $posts = $posts->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('title', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $posts = $posts->paginate(10);

        return withSuccessResourceList(CaseStudyResource::collection($posts));
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
            'title',
            'auth_name',
            'description',
        ]);

        if($request->hasFile('attachments')){
            $upload = $request->file('attachments');

            $extension = $upload->getClientOriginalExtension();
            $filename = md5(time()).'_thumb_'.$upload->getClientOriginalName();

            $normal = Image::read($upload)->scale(width: 300)->encode();

            $thumbPath = Storage::disk('s3')->put('upload/case-study/'.$filename, $normal->__toString() );
            $originalPath = Storage::disk('s3')->put('upload/case-study', $upload );

            $data['attachment'] = [
                'thumb'     => 'upload/case-study/'.$filename,
                'original'  => $originalPath
            ];
        }

        $post = CaseStudy::create($data);

        if( is_array($request->categories) && !empty($request->categories)){
            $post->categories()->attach($request->categories);
        }

        if( is_array($request->tags) && !empty($request->tags)){
            $post->tags()->attach($request->tags);
        }

        // return with success response
        return withSuccess(new CaseStudyResource($post), 'Case Study created successfully');
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
        $post = CaseStudy::where('tag', $tag)->first();

        if (!$post) {
            return withError('Invalid Case Study Tag');
        }

        return withSuccess(new CaseStudyResource($post));
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
        $post = CaseStudy::where('tag', $tag)->firstOrFail();

        if (!$post) {
            return withError('Invalid Case Study Tag');
        }
        $data = $request->only([
            'title',
            'auth_name',
            'description',
        ]);

        if($request->hasFile('attachments')){
            $upload = $request->file('attachments');

            $extension = $upload->getClientOriginalExtension();
            $filename = md5(time()).'_thumb_'.$upload->getClientOriginalName();

            $normal = Image::read($upload)->scale(width: 300)->encode();

            $thumbPath = Storage::disk('s3')->put('upload/case-study/'.$filename, $normal->__toString() );
            $originalPath = Storage::disk('s3')->put('upload/case-study', $upload );

            $data['attachment'] = [
                'thumb'     => 'upload/case-study/'.$filename,
                'original'  => $originalPath
            ];

            foreach( $post->attachment as $key=>$oldFile ){
                Storage::disk('s3')->delete($oldFile);
            }
        }

        $post->update($data);

        if( is_array($request->categories) && !empty($request->categories)){
            $post->categories()->sync($request->categories);
        }

        if( is_array($request->tags) && !empty($request->tags)){
            $post->tags()->sync($request->tags);
        }


        return withSuccess(new CaseStudyResource($post), 'Case Study updated successfully');
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
        $post = CaseStudy::where('tag', $tag)->first();

        if (!$post) {
            return withError('Invalid Case Study Tag');
        }

        $post->categories()->detach();
        $post->tags()->detach();

        $post->delete();

        return withSuccess(message: 'Case Study deleted successfully');
    }
}
