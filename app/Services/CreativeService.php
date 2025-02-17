<?php

namespace App\Services;

use App\Mail\CreativeApprovedRejectedEmail;
use App\Models\CreativeUpload;
use App\Traits\FormatterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CreativeService
{
    use FormatterTrait;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Sends an creative changes required email to the user who submitted.
     *
     * @param Request $request
     * @param CreativeUpload $creative
     * @return void
     */
    public function sendCreativeChangeRequiredMail(Request $request, CreativeUpload $creative)
    {

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
                ->send(new CreativeApprovedRejectedEmail(
                    [
                        'status'    => $creative->status,
                        'template' => 'mails.creative_changes_required',
                        'subject' => 'Your Creative ' . $creative->name . ' needs to changes required',
                        'message' => [
                            'emailData' => $emailData
                        ],
                    ]
                ));

    }

    /**
     * Sends an creative Approved email to the user who submitted.
     *
     * @param Request $request
     * @param CreativeUpload $creative
     * @return void
     */
    public function sendCreativeApprovedMail(Request $request, CreativeUpload $creative)
    {

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
                ->send(new CreativeApprovedRejectedEmail(
                    [
                        'status'    => $creative->status,
                        'template' => 'mails.creative_approved',
                        'subject' => 'Your Creative ' . $creative->name . ' is approved',
                        'message' => [
                            'emailData' => $emailData
                        ],
                    ]
                ));

    }


    /**
     * Sends an creative Rejected email to the user who submitted.
     *
     * @param Request $request
     * @param CreativeUpload $creative
     * @return void
     */
    public function sendCreativeRejectedMail(Request $request, CreativeUpload $creative)
    {

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
                ->send(new CreativeApprovedRejectedEmail(
                    [
                        'status'    => $creative->status,
                        'template' => 'mails.creative_rejected',
                        'subject' => 'Your Creative ' . $creative->name . ' is rejected',
                        'message' => [
                            'emailData' => $emailData
                        ],
                    ]
                ));

    }


    // public function sendCreativeApprovedMail(Request $request, CreativeUpload $creative)
    // {
    //     $emailData = $this->formatEmailData($invoice);

    //     Mail::to($invoice->user->email)
    //         ->queue(new InvoiceRejectionMail($emailData));

    // }
}
