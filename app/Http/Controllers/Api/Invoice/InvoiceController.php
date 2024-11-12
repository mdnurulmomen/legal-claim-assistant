<?php

namespace App\Http\Controllers\Api\Invoice;

use App\Helpers\Utility;
use App\Http\Controllers\Api\Invoice\Resources\InvoiceListResource;
use App\Http\Controllers\Api\Invoice\Resources\InvoiceResource;
use App\Http\Controllers\Controller;
use App\Mail\InvoiceRejectionMail;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    use CommonTrait;

    /**
     * Retrieves a list of platforms based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function invoiceList(Request $request): Response
    {
        $invoices = Invoice::query();

        $invoices = $invoices->whereHas('user', function($query){
            $query->where('role', 'affiliate');
        });

        $invoices->when( $request->has('status') && (!empty($request->status) && $request->status != "all"), function ($query) use ($request) {
            $query_status = explode(',', $request->status);

            $formatedstatus = [];
            foreach ($query_status as $status) {
                $formatedstatus[] = str_replace('-', ' ', $status);
            }

            $query->whereIn('status', $formatedstatus);
        });

        if ($request->has('partner') && $request->get('partner') != "all") {
            $query_partners = explode(',', $request->get('partner'));

            $invoices = $invoices->whereIn('user_id', $query_partners);
        }

        if ($request->has('payment_term') && $request->get('payment_term') != "all") {
            $query_terms = explode(',', $request->get('payment_term'));

            $invoices = $invoices->whereIn('monthly_net', $query_terms);
        }

        if ($request->has('date_e') && $request->has('date_s')) {

            try {
                $date_s = Carbon::createFromFormat('Y-m-d H:i:s', $request->date_s)->startOfDay();
                $date_e = Carbon::createFromFormat('Y-m-d H:i:s', $request->date_e)->endOfDay();
            } catch (\Throwable $th) {
                abort(404);
            }

            $invoices = $invoices->where([['created_at', '<', $date_e], ['created_at', '>=', $date_s]]);
        }

        // $invoices_total = $invoices->count();
        $invoices = $invoices->orderBy('created_at', 'DESC')->latest()->paginate(20);

        return withSuccessResourceList(InvoiceListResource::collection($invoices));
    }


    /**
     * Retrieves a invoice based on the request parameter tag.
     *
     * @param Request $request
     * @return Response
     */
    public function invoiceByTag(Request $request, $tag): Response
    {
        $invoices = Invoice::where('tag', $tag)->get();

        if($invoices){
            return withSuccessResourceList(InvoiceResource::collection($invoices));
        }
        return withError('Invalid Invoice request.');
    }

    public function invoiceUpdate(Request $request, $tag)
    {

        $allowStatus = ['Paid', 'Unpaid', 'Rejected'];
        $invoice = Invoice::where('tag', $tag)->with('partner')->first();

        if (!$invoice) {
            return withError('Invalid Invoice request.');
        }

        if (in_array($request->get('status'), $allowStatus)) {

            // update the status
            $invoice->status = $request->get('status');

            // if status is switch to paid looking for proof file
            if ($request->get('status') == 'Paid') {
                $invoice->status == "Unpaid";
            } else if($request->get('status') == 'Unpaid') {
                $invoice->status == "Unpaid";
            } else if($request->get('status') == 'Rejected') {
                $invoice->status == "Rejected";
            }
            $invoice->update();

            return withSuccess('Updated Successfully');
        }
    }

    /**
     * Retrieves the available invoice statuses and returns them in a successful response.
     *
     * @param Request $request
     * @return Response
     */
    public function invoiceStatus(Request $request)
    {
        $statuses = $this->convertToMultiDimensionalArray(Utility::$invoiceStatuses);
        return withSuccess($statuses);
    }

    /**
     * Updates the status of an invoice with the given ID.
     *
     * @param Request $request
     * @param int $invoiceId
     * @return Response
     */
    public function updateInvoiceStatus(Request $request, $invoiceId, InvoiceService $invoiceService): Response
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', Rule::in(array_keys(Utility::$invoiceStatuses))],
            'comment' => [ 'required_if:status,Rejected', 'nullable', 'string',],
        ]);

        if ($validator->fails()) {
            return withError($validator->errors()->first());
        }

        $invoice = Invoice::find($invoiceId);

        if(empty($invoice)){
            return withError('Invalid Invoice request.');
        }

        $invoice->status = $request->status;
        $invoice->comment = $request->comment ?? null;

        $invoice->save();

        $invoiceService->sendInvoiceRejectionMail($request);

        return withSuccess(message:'Invoice Status Updated Successfully');
    }

}
