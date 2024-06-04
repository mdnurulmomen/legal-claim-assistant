<?php

namespace App\Http\Controllers\Api\Reporting;

use App\Helpers\Utility;
use App\Http\Controllers\Api\Reporting\Resources\ReportingResource;
use App\Http\Controllers\Controller;
use App\Models\LeadReport;
use App\Models\PlatformData;
use App\Models\PlatformList;
use App\Models\User;
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
        [$relationalConditions, $conditions] = $reportingService->formatFilters($request);

        $subQuery = LeadReport::selectRaw("
                        pl.name as platform_name,
                        buyers.name as buyer_name,
                        affiliate.name as affiliate_name,
                        lead_reports.affid,
                        lead_reports.list_id,
                        lead_reports.buyer_id,
                        lead_reports.affiliate_id,
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
                    ->leftJoin('platform_lists as pl', 'lead_reports.list_id', '=', 'pl.id')
                    ->leftJoin('buyers', 'lead_reports.buyer_id', '=', 'buyers.id')
                    ->leftJoin('users as affiliate', 'lead_reports.affiliate_id', '=', 'affiliate.id')
                    ->when(in_array('lead_reports.affid', $groupBy), function ($query) {
                        return $query->whereNotNull('lead_reports.affid');
                    })
                    ->when(in_array('lead_reports.buyer_id', $groupBy), function ($query) {
                        return $query->whereNotNull('lead_reports.buyer_id');
                    })
                    ->when(in_array('lead_reports.list_id', $groupBy), function ($query) {
                        return $query->whereNotNull('lead_reports.list_id');
                    })
                    ->when(in_array('lead_reports.affiliate_id', $groupBy), function ($query) {
                        return $query->whereNotNull('lead_reports.affiliate_id');
                    })
                    ->when(! empty($relationalConditions), function ($query) use ($relationalConditions, $reportingService) {
                        return $query->where(function ($query) use ($relationalConditions, $reportingService) {
                            foreach ($relationalConditions as $index => $condition) {
                                $method = $reportingService->getConditionMethod($index);
                                $query->$method($condition['column'], $condition['operator'], $condition['value']);
                            }
                        });
                    })
                    ->when(! empty($request->start_date) && ! empty($request->end_date), function ($query) use ($request) {
                        return $query->whereBetween('lead_reports.created_at', [$request->start_date, $request->end_date]);
                    })
                    ->groupBy($groupBy);

        $leads = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
                            ->mergeBindings($subQuery->getQuery()) // Ensure bindings are merged correctly
                            ->selectRaw("
                                sub.*,
                                FORMAT((sub.accepted / NULLIF(sub.posted, 0)) * 100, 2) as acceptance_rate,
                                FORMAT((sub.accepted_cpl / NULLIF(sub.posted, 0)) * 100, 2) as acceptance_rate_cpl
                            ")
                            ->when(! empty($orderBy) && ! empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                                return $query->orderBy($orderBy, $orderIn);
                            })
                            ->when(! empty($conditions), function ($query) use ($conditions, $reportingService) {
                                return $query->where(function ($query) use ($conditions, $reportingService) {
                                    foreach ($conditions as $conditionKey => $conditionGroup) {

                                        $method = $reportingService->getConditionMethod($conditionKey);

                                        $query->$method(function ($query2) use ($conditionGroup, $reportingService) {

                                            foreach ($conditionGroup as $index => $condition) {

                                                $type = $reportingService->getConditionType($condition['operator']);
                                                $method2 = $reportingService->getConditionMethod($index, $type);

                                                if($type) {
                                                    $query2->$method2($condition['column']);
                                                    continue;
                                                }

                                                $query2->$method2($condition['column'], $condition['operator'], $condition['value']);
                                            }
                                        });
                                    }
                                });
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

    public function getFilterDropdownValues(Request $request)
    {
        $lists = PlatformList::select('id as value', 'name as label')->get();
        $buyers = DB::table('buyers')->select('id as value', 'name as label')->get();
        $affiliates = User::select('id as value', 'name as label')->where('role', 'affiliate')->get();
        $affIds = LeadReport::select('affid as value', 'affid as label')->whereNotNull('affid')->groupBy('affid')->get();

        return withSuccess(compact('lists', 'buyers', 'affiliates', 'affIds'));
    }

}
