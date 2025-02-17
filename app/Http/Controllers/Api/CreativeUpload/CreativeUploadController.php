<?php

namespace App\Http\Controllers\Api\CreativeUpload;

use App\Helpers\Utility;
use App\Http\Controllers\Api\CreativeUpload\Resources\CreativeUploadResource;
use App\Http\Controllers\Controller;
use App\Models\CreativeUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\CreativeRejectionMail;
use App\Services\CreativeService;

class CreativeUploadController extends Controller
{
    /**
     * Retrieves a list of creative upload based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $creatives = CreativeUpload::with('user')->orderBy('created_at', 'desc');
        $creatives = $creatives->when( !empty($request->search_txt), function ($query) use ($request) {
            return $query->where('name', 'like', '%' . $request->search_txt . '%');
        });

        //paginate
        $creatives = $creatives->paginate(10, ['*'], 'page', $request->page ?? 1);

        return withSuccessResourceList(CreativeUploadResource::collection($creatives));
    }

    /**
     * Retrieves a single creative upload based on the provided tag.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, $tag)
    {
        $creative = CreativeUpload::where('tag', $tag)->first();
        return withSuccess(new CreativeUploadResource($creative));
    }

    /**
     * Updates the creative upload based on the provided tag.
     *
     * @param Request $request
     * @param string $tag
     * @return Response
     */
    public function update(Request $request, $tag, CreativeService $creativeService)
    {
        $creative = CreativeUpload::where('tag', $tag)->first();

        $creative->update(['status' => $request->status]);

        if( in_array($request->status, ['under review', 'changes required'])){
            foreach($request->attachments as $id => $status){
                $creative->creative_attachments()->where('id', $id)->update(['status' => $status]);
            }

            $creativeService->sendCreativeChangeRequiredMail($request, $creative);
        }

        if( $request->status == 'approved'){
            $creative->creative_attachments()->update(['status' => 'accepted']);
            $creativeService->sendCreativeApprovedMail($request, $creative);
        }

        if( $request->status == 'rejected'){
            $creative->creative_attachments()->update(['status' => 'rejected']);
            $creativeService->sendCreativeRejectedMail($request, $creative);
        }

        return withSuccess(new CreativeUploadResource($creative), message: 'Creative Updated Successfully');
    }

    /**
     * approved the creative upload based on the provided tag.
     *
     * @param Request $request
     * @param string $tag
     * @return Response
     */
    public function approved(Request $request, $tag, CreativeService $creativeService)
    {
        $creative = CreativeUpload::where('tag', $tag)->first();

        $creative->update(['status' => 'approved']);
        $creative->creative_attachments()->update(['status' => 'accepted']);

        $creativeService->sendCreativeApprovedMail($request, $creative);

        return withSuccess(message: 'Creative approved successfully');
    }

    /**
     * rejected the creative upload based on the provided tag.
     *
     * @param Request $request
     * @param string $tag
     * @return Response
     */
    public function rejected(Request $request, $tag, CreativeService $creativeService)
    {
        $creative = CreativeUpload::where('tag', $tag)->first();

        $creative->update(['status' => 'rejected']);
        $creative->creative_attachments()->update(['status' => 'rejected']);

        $creativeService->sendCreativeRejectedMail($request, $creative);

        // Mail::to($toEmail)
        //     ->send(new CreativeRejectionMail($emailData));
        return withSuccess(message: 'Creative rejected successfully');
    }
}
