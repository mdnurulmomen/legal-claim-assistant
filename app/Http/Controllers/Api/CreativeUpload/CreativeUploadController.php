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
use App\Mail\CreativeApprovedRecectedEmail;
use App\Mail\CreativeRejectionMail;

class CreativeUploadController extends Controller
{
    /**
     * Retrieves a list of Conference Event based on the request parameters.
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
     * Retrieves a single Conference Event based on the provided ID.
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
     * Updates the Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function approved(Request $request, $tag)
    {
        $creative = CreativeUpload::where('tag', $tag)->first();

        $creative->update(['status' => 'approved']);

        $creativeUser = $creative->user;
        $toEmail = $creativeUser->email;

        $emailData = [
            'company_name' => 'Legal Claim Assistant',
            'company_logo' => asset('/assets/images/logo.png'),
            'username' => $creativeUser->name,
            'creative_name' => $creative->name,
            'submitted_date' => $creative->created_at->format('d M Y')
        ];

        Mail::to($toEmail)
                ->send(new CreativeApprovedRecectedEmail(
                    [
                        'status'    => $creative->status,
                        'template' => 'mails.creative_approved',
                        'subject' => 'Creative is approved ( ' . $creative->name . ' )',
                        'message' => [
                            'emailData' => $emailData
                        ],
                    ]
                ));
        return withSuccess(message: 'Creative approved successfully');
    }

    /**
     * Updates the Conference Event based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function rejected(Request $request, $tag)
    {
        $creative = CreativeUpload::where('tag', $tag)->first();

        $creative->update(['status' => 'rejected']);



        $creativeUser = $creative->user;
        $toEmail = $creativeUser->email;

        $emailData = [
            'company_name' => 'Legal Claim Assistant',
            'company_logo' => asset('/assets/images/logo.png'),
            'username' => $creativeUser->name,
            'creative_name' => $creative->name,
            'submitted_date' => $creative->created_at->format('d M Y')
        ];

        Mail::to($toEmail)
                ->send(new CreativeApprovedRecectedEmail(
                    [
                        'status'    => $creative->status,
                        'template' => 'mails.creative_rejected',
                        'subject' => 'Creative is rejected ( ' . $creative->name . ' )',
                        'message' => [
                            'emailData' => $emailData
                        ],
                    ]
                ));

        // Mail::to($toEmail)
        //     ->send(new CreativeRejectionMail($emailData));
        return withSuccess(message: 'Creative rejected successfully');
    }
}
