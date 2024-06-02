<?php

namespace App\Http\Controllers\Api\Reporting;

use App\Helpers\Utility;
use App\Http\Controllers\Api\Reporting\Resources\ReportingResource;
use App\Http\Controllers\Controller;
use App\Models\LeadReport;
use App\Models\PlatformData;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReportingController extends Controller
{

    /**
     * Retrieves a paginated list of reporting data based on the given request parameters.
     *
     * @param Request $request
     * @param ReportingService $reportingService
     * @return Response
     */
    public function reportingList(Request $request, ReportingService $reportingService): Response
    {
        $limit = $request->get('limit', 10);
        [$orderBy, $orderIn] = $reportingService->formatOrderByIn($request);
        $groupBy = $reportingService->formatGroupBy($request);

        $subQuery = LeadReport::selectRaw("
                        lead_reports.list_id,
                        COUNT(CASE WHEN lead_reports.is_posted = 1 THEN 1 END) as posted,
                        COUNT(CASE WHEN lead_reports.buyer_id IS NOT NULL AND lead_reports.is_posted = 1 THEN 1 END) as accepted,
                        COUNT(CASE WHEN lead_reports.buyer_id IS NULL AND lead_reports.is_posted = 1 THEN 1 END) as rejected,
                        COUNT(CASE WHEN lead_reports.sold_type = 'CPL' THEN 1 END) as accepted_cpl,
                        SUM(lead_reports.lead_revenue) as revenue,
                        SUM(lead_reports.lead_profit) as profit,
                        SUM(lead_reports.affiliate_payout) as affiliate_payout,
                        AVG(lead_reports.lead_revenue) as revenue_per_lead,
                        AVG(lead_reports.lead_profit) as average_profit,
                        AVG(lead_reports.affiliate_payout) as affiliate_average_payout
                    ")
                    ->leftJoin('platform_datas', function ($join) use ($request) {
                        $join->on('lead_reports.lead_id', '=', 'platform_datas.id')
                            ->when(! empty($request->start_date) && ! empty($request->end_date), function ($query) use ($request) {
                                return $query->whereBetween('platform_datas.created_at', [$request->start_date, $request->end_date]);
                            });
                    })
                    ->groupBy($groupBy);

        $leads = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
                            ->mergeBindings($subQuery->getQuery()) // Ensure bindings are merged correctly
                            ->selectRaw("
                                pl.name as platform_name,
                                sub.*,
                                FORMAT((sub.accepted / NULLIF(sub.posted, 0)) * 100, 2) as acceptance_rate,
                                FORMAT((sub.accepted_cpl / NULLIF(sub.posted, 0)) * 100, 2) as acceptance_rate_cpl
                            ")
                            ->leftJoin('platform_lists as pl', 'sub.list_id', '=', 'pl.id')
                            ->when(! empty($orderBy) && ! empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                                return $query->orderBy($orderBy, $orderIn);
                            })
                            ->paginate($limit);

        return withSuccessResourceList(ReportingResource::collection($leads));
    }

    /**
     * Retrieves the reporting tabs formatted by the ReportingService.
     *
     * @param Request $request
     * @param ReportingService $reportingService
     * @return Response
     */
    public function getReportingTabs(Request $request, ReportingService $reportingService): Response
    {
        return withSuccess($reportingService->formatReportingTabs(Utility::$reportTabs));
    }

}
