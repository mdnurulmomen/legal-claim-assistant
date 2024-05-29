<?php

namespace App\Http\Controllers\Api\Reporting;

use App\Http\Controllers\Api\Reporting\Resources\ReportingResource;
use App\Http\Controllers\Controller;
use App\Models\PlatformData;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReportingController extends Controller
{

    public function reportingList(Request $request, ReportingService $reportingService): Response
    {
        $limit = $request->get('limit', 10);

        [$orderBy, $orderIn] = $reportingService->formatOrderByIn($request); //Todo:: implement proper validation

        $subQuery = PlatformData::selectRaw("
                            platform_datas.list_id,
                            COUNT(platform_datas.id) as posted,
                            COUNT(CASE WHEN platform_datas.is_sold = 1 THEN 1 END) as accepted,
                            COUNT(CASE WHEN platform_datas.is_sold = 0 THEN 1 END) as rejected,
                            COUNT(CASE WHEN platform_datas.sold_type = 'CPL' THEN 1 END) as accepted_cpl,
                            SUM(lead_reports.lead_revenue) as revenue,
                            SUM(lead_reports.lead_profit) as profit,
                            SUM(lead_reports.affiliate_payout) as affiliate_payout,
                            AVG(lead_reports.lead_revenue) as revenue_per_lead,
                            AVG(lead_reports.lead_profit) as average_profit,
                            AVG(lead_reports.affiliate_payout) as affiliate_average_payout
                        ")
                        ->leftJoin('lead_reports', 'platform_datas.id', '=', 'lead_reports.lead_id')
                        ->groupBy('platform_datas.list_id');

                $leads = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
                            ->mergeBindings($subQuery->getQuery()) // Ensure bindings are merged correctly
                            ->selectRaw("
                                pl.name as platform_name,
                                sub.posted,
                                sub.accepted,
                                sub.rejected,
                                sub.accepted_cpl,
                                sub.revenue,
                                sub.profit,
                                sub.affiliate_payout,
                                sub.revenue_per_lead,
                                sub.average_profit,
                                sub.affiliate_average_payout,
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

}
