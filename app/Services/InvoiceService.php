<?php

namespace App\Services;

use App\Mail\InvoiceRejectionMail;
use App\Models\Invoice;
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

    public function formatEmailData(Invoice $invoice)
    {
        $emailData = [
            'username' => $invoice->user->name, // invoice->user->name
            'invoice_no' => $invoice->tag, // invoice->tag
            'invoice_amount' => $invoice->amount, // invoice->amount
            'submitted_date' => $invoice->created_at, // invoice->created_at
            'rejection_reason' => $invoice->comment, // invoice->comment
            'company_name' => 'Legal Claim Assistant'
        ];

        return $emailData;
    }

    public function sendInvoiceRejectionMail(Request $request, Invoice $invoice)
    {
        $emailData = $this->formatEmailData($invoice);

        Mail::to($invoice->user->email) // invoice->user->email
            ->queue(new InvoiceRejectionMail($emailData));

    }
}
