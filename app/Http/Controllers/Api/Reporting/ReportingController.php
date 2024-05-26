<?php

namespace App\Http\Controllers\Api\Reporting;

use App\Http\Controllers\Api\Reporting\Resources\ReportingResource;
use App\Http\Controllers\Controller;
use App\Models\LeadReport;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportingController extends Controller
{

    public function reportingList(Request $request, ReportingService $reportingService): Response
    {
        $limit = $request->get('limit', 10);

        [$orderBy, $orderIn] = $reportingService->formatOrderByIn($request);

        $reports = LeadReport::selectRaw("
                        lead_reports.lead_id,
                        MAX(pl.name) as platform_name,
                        SUM(lead_reports.lead_revenue) as revenue,
                        AVG(lead_reports.lead_revenue) as revenue_per_lead,
                        SUM(lead_reports.lead_profit) as profit,
                        AVG(lead_reports.lead_profit) as average_profit,
                        SUM(lead_reports.affiliate_payout) as affiliate_payout,
                        AVG(lead_reports.affiliate_payout) as affiliate_average_payout
                    ")
                    ->leftJoin('platform_lists as pl', 'lead_reports.list_id', '=', 'pl.id')
                    ->when(! empty($orderBy) && ! empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                        return $query->orderByRaw("{$orderBy} {$orderIn}");
                    })
                    ->groupBy('lead_reports.lead_id')
                    ->paginate($limit);

        return withSuccessResourceList(ReportingResource::collection($reports));
    }

}
