<?php

namespace App\Http\Controllers\Api\ReleaseNote;

use App\Http\Controllers\Api\ReleaseNote\Resources\ReleaseNoteResource;
use App\Http\Controllers\Api\ReleaseNote\Requests\CreateOrUpdateReleaseNoteRequest;
use App\Http\Controllers\Controller;
use App\Models\ReleaseNote;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ReleaseNoteController extends Controller
{

    /**
     * Retrieves a list of release note based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function releaseNoteList(Request $request): Response
    {
        $limit = $request->input('limit', 10);
        $orderBy = $request->input('order_by');
        $orderIn = $request->input('order_in'); 

        $releaseNoteList = ReleaseNote::query()
                        ->orderBy('id', 'desc')
                        ->paginate($limit);

        return withSuccessResourceList(ReleaseNoteResource::collection($releaseNoteList));
    }

    /**
     * Creates a release note based on the provided request data.
     *
     * @param CreateOrUpdateReleaseNoteRequest $request
     * @return Response
     */
    public function createReleaseNote(CreateOrUpdateReleaseNoteRequest $request): Response
    {
        $releaseNote = ReleaseNote::create($request->validated());
        return withSuccess(new ReleaseNoteResource($releaseNote), 'Release note created successfully');
    }
    
    /**
     * Retrieves and shows the note info based on the provided ID.
     *
     * @param Request $request
     * @param Request $noteId
     * @return Response
     */
    public function releaseNote(Request $request, int $noteId): Response
    {
        $releaseNote = ReleaseNote::find($noteId);

        return withSuccess($releaseNote);
    }
    
    /**
     * Updates a note based on the provided note ID and request data.
     *
     * @param CreateOrUpdateReleaseNoteRequest $request
     * @param int $noteId
     * @return Response
     */
    public function updateReleaseNote(CreateOrUpdateReleaseNoteRequest $request, int $noteId): Response
    {
        $releaseNote = $releaseNote = ReleaseNote::find($noteId);
        if(empty($releaseNote)){
            return withError('Release note not found', 404);
        }

        $formattedData = $request->validated();

        $releaseNote->update($formattedData);
        return withSuccess(new ReleaseNoteResource($releaseNote->refresh()), 'Release note updated successfully');
    }
    
    /**
     * Delete note by id in the request.
     *
     * @param Request $request
     * @param Request $id
     * @return Response
     */
    public function releaseNoteDelete(Request $request, int $noteId): Response
    {
        $releaseNote = ReleaseNote::find($noteId);
        
        if($releaseNote){
            $releaseNote->delete();
        }

        return withSuccess(message: 'Release note deleted successfully');
    }
    
    
    /**
     * Upload images for note in the request.
     *
     * @param Request $request 
     * @return Response
     */
    public function uploadImage(Request $request): Response
    {
        $request->validate([
            'upload'=> ['required','max:200'] 
        ]); 

        // Handle file Upload
        if($request->hasFile('upload')){

            //Storage::delete('/public/avatars/'.$user->avatar);

            // Get filename with the extension
            $filenameWithExt = $request->file('upload')->getClientOriginalName();
            //Get just filename
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            // Get just ext
            $extension = $request->file('upload')->getClientOriginalExtension();
            // Filename to store
            $fileNameToStore = $filename.'_'.time().'.'.$extension;
            // Upload Image
            $path = $request->file('upload')->storeAs('public/releaseNote',$fileNameToStore);

            $fileWithUrl = asset( '/storage/releaseNote/'.$fileNameToStore ); 
        }
        return withSuccess([ 'file' => $fileWithUrl ]);
    }
    

}
