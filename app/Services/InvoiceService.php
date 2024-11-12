<?php

namespace App\Services;

use App\Mail\InvoiceRejectionMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InvoiceService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function formatEmailData()
    {
        $emailData = [
            'username' => 'Test User', // invoice->user->name
            'invoice_no' => 'in-48594', // invoice->tag
            'invoice_amount' => '5000', // invoice->amount
            'submitted_date' => '2023-02-28', // invoice->created_at
            'rejection_reason' => 'Some text will go here', // invoice->comment
            'company_name' => 'Legal Claim Assistant'
        ];

        return $emailData;
    }

    public function sendInvoiceRejectionMail(Request $request)
    {

        $emailData = $this->formatEmailData($request);

        Mail::to('subhesadek89990@gmail.com') // invoice->user->email
            ->queue(new InvoiceRejectionMail($emailData));

    }
}
