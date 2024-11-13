<?php

namespace App\Services;

use App\Mail\InvoiceRejectionMail;
use App\Models\Invoice;
use App\Traits\FormatterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InvoiceService
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
     * Formats email data for an invoice rejection notice.
     *
     * @param Invoice $invoice
     * @return array
     */
    public function formatEmailData(Invoice $invoice): array
    {
        return [
            'username' => $invoice->user->name,
            'invoice_no' => $invoice->tag,
            'invoice_amount' => ($invoice->currency ? $invoice->currency . ' ' : '') . number_format($invoice->amount, 2), //$invoice->amount,
            'submitted_date' => $this->formatDateTime($invoice->created_at),
            'rejection_reason' => $invoice->comment,
            'company_name' => 'Legal Claim Assistant'
        ];
    }

    /**
     * Sends an invoice rejection email to the user who submitted the invoice.
     *
     * @param Request $request
     * @param Invoice $invoice
     * @return void
     */
    public function sendInvoiceRejectionMail(Request $request, Invoice $invoice)
    {
        $emailData = $this->formatEmailData($invoice);

        Mail::to($invoice->user->email)
            ->queue(new InvoiceRejectionMail($emailData));

    }
}
