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
            'username' => 'Test User',
            'invoice_no' => 'in-48594',
            'invoice_amount' => '5000',
            'submitted_date' => '2023-02-28',
            'rejection_reason' => 'Some text will go here',
            'company_name' => 'legalClaimAssistance'
        ];

        return $emailData;
    }

    public function sendInvoiceRejectionMail(Request $request)
    {

        $emailData = $this->formatEmailData($request);

        Mail::to('subhesadek89990@gmail.com')
            ->queue(new InvoiceRejectionMail($emailData));

    }
}
