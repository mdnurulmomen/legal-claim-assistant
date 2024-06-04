<?php

namespace App\Http\Controllers\Api\Invoice;

use App\Http\Controllers\Api\Invoice\Resources\InvoiceListResource;
use App\Http\Controllers\Api\Invoice\Resources\InvoiceResource;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * Retrieves a list of platforms based on the request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function invoiceList(Request $request): Response
    {
        $invoices = Invoice::query();
        $invoices = $invoices->with('listresult', 'partner.partner');
        
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

}
