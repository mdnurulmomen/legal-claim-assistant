<?php

namespace App\Http\Controllers\Api\Reporting;

use App\Helpers\Utility;
use App\Http\Controllers\Api\Reporting\Resources\ReportingResource;
use App\Http\Controllers\Controller;
use App\Models\LeadReport;
use App\Models\PlatformList;
use App\Models\User;
use App\Services\ExcelService;
use App\Services\ReportingService;
use App\Traits\CommonTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    use CommonTrait;

    /**
     * Retrieves a paginated list of reporting data based on the given request parameters.
     *
     * @param Request $request
     * @param ReportingService $reportingService
     * @return Response | string | StreamedResponse
     */
    public function reportingList(Request $request, ReportingService $reportingService, ExcelService $excelService): Response | string | StreamedResponse
    {
        $limit = $request->get('limit', 10);
        [$orderBy, $orderIn] = $reportingService->formatOrderByIn($request);
        $groupBy = $reportingService->formatGroupBy($request);
        [$relationalConditions, $conditions] = $reportingService->formatFilters($request);

        [$reportStart, $reportEnd] = $this->formatStartEndDateWithTimezone($request->start_date, $request->end_date, $request->timezone);

        $subQuery = LeadReport::selectRaw("
                        lead_reports.id,
                        pl.name as platform_name,
                        buyers.name as buyer_name,
                        integrations.name as integration_name,
                        affiliate.name as affiliate_name,
                        JSON_EXTRACT(affiliate.data, '$.affids') AS affids,
                        lead_reports.affid,
                        pd.retained_date,
                        COUNT(CASE WHEN lead_reports.is_posted = 1 THEN 1 END) as posted,
                        COUNT(CASE WHEN lead_reports.buyer_id IS NOT NULL AND lead_reports.is_posted = 1 THEN 1 END) as accepted,
                        COUNT(CASE WHEN lead_reports.buyer_id IS NULL AND lead_reports.is_posted = 1 THEN 1 END) as rejected,
                        COUNT(CASE WHEN lead_reports.is_retainer > 0 THEN 1 END) as retained,
                        COUNT(CASE WHEN lead_reports.sold_type = 'CPL' THEN 1 END) as accepted_cpl,
                        AVG(CASE WHEN pd.retained_date IS NOT NULL THEN IF(DATEDIFF(pd.retained_date, pd.created_at) < 0, 0, DATEDIFF(pd.retained_date, pd.created_at)) END) AS avg_retain_time,
                        COUNT(CASE WHEN lead_reports.is_retainer > 0 THEN 1 END) / COUNT(CASE WHEN lead_reports.buyer_id IS NOT NULL AND lead_reports.is_posted = 1 THEN 1 END) * 100 as avg_retained_leads,
                        SUM(lead_reports.lead_revenue) as revenue,
                        SUM(lead_reports.lead_profit) as profit,
                        SUM(lead_reports.affiliate_payout) as affiliate_payout,
                        AVG(lead_reports.lead_revenue) as revenue_per_lead,
                        AVG(lead_reports.lead_profit) as average_profit,
                        AVG(lead_reports.affiliate_payout) as affiliate_average_payout
                    ")
                    ->leftJoin('platform_datas as pd', 'lead_reports.lead_id', '=', 'pd.id')
                    ->leftJoin('platform_lists as pl', 'lead_reports.list_id', '=', 'pl.id')
                    ->leftJoin('buyers', 'lead_reports.buyer_id', '=', 'buyers.id')
                    ->leftJoin('integrations', 'lead_reports.buyer_integration_id', '=', 'integrations.id')
                    ->leftJoin('users as affiliate', 'lead_reports.affiliate_id', '=', 'affiliate.id')
                    ->when(! empty($relationalConditions), function (Builder $query) use ($relationalConditions, $reportingService) {
                        return $reportingService->convertRelationsToSql($query, $relationalConditions);
                    })
                    ->when(! empty($reportStart) && ! empty($reportEnd), function ($query) use ($reportStart, $reportEnd) {
                        return $query->whereBetween('lead_reports.created_at', [$reportStart, $reportEnd]);
                    })
                    ->when(! empty($request->is_retained_only), function ($query) {
                        return $query->where('pd.is_retainer', '>', 0);
                    })
                    ->groupBy($groupBy);

        $baseQuery = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
                            ->mergeBindings($subQuery->getQuery()) // Ensure bindings are merged correctly
                            ->selectRaw("sub.*")
                            ->when(empty($request->is_total), function ($query) {
                                return $query->selectRaw("
                                    FORMAT((sub.accepted / NULLIF(sub.posted, 0)) * 100, 2) as acceptance_rate,
                                    FORMAT((sub.accepted_cpl / NULLIF(sub.posted, 0)) * 100, 2) as acceptance_rate_cpl
                                ");
                            })
                            ->when(! empty($orderBy) && ! empty($orderIn), function ($query) use ($orderBy, $orderIn) {
                                return $query->orderBy($orderBy, $orderIn);
                            })
                            ->when(! empty($conditions), function ($query) use ($conditions, $reportingService) {
                                return $query->where(function ($query) use ($conditions, $reportingService) {
                                    foreach ($conditions as $conditionKey => $conditionGroup) {
                                        $query->where(function ($query2) use ($conditionGroup, $reportingService) {

                                            foreach ($conditionGroup as $index => $condition) {

                                                $type = $reportingService->getConditionType($condition['operator']);
                                                $method = $reportingService->getConditionMethod($index, $type);

                                                if($type) {
                                                    $query2->$method($condition['column']);
                                                    continue;
                                                }

                                                $query2->$method($condition['column'], $condition['operator'], $condition['value']);
                                            }
                                        });
                                    }
                                });
                            });

        if(! empty($request->is_total)) {
            $leads = $reportingService->getReportTotals($baseQuery, $request);
            return withSuccess($leads);
        }

        if(! empty($request->is_export)){
            return $excelService->exportReportData($request, $baseQuery);
        }

        $leads = $baseQuery->paginate($limit);

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

    /**
     * Retrieves the filter dropdown values based on the given request.
     *
     * @param Request $request
     * @return Response
     */
    public function getFilterDropdownValues(Request $request)
    {
        $lists = PlatformList::select('id as value', 'name as label')->get();
        $buyers = DB::table('buyers')->select('id as value', 'name as label')->get();
        $affiliates = User::select('id as value', 'name as label', 'data->affids as affids', 'role')
                        ->where('role', 'affiliate')
                        ->get()
                        ->map(function ($user) {
                            if(! hasAffiliateAccess() && $user->role === 'affiliate') {
                                return [
                                    'value' => $user->value,
                                    'label' => $user->affids ? implode(', ', json_decode($user->affids, true)) : ''
                                ];
                            }

                            return [
                                'value' => $user->value,
                                'label' => $user->name
                            ];
                        })->toArray();

        $affIds = LeadReport::select('affid as value', 'affid as label')->whereNotNull('affid')->groupBy('affid')->get();

        $buyer_integrations = DB::table('integrations')
                                ->leftJoin('platform_lists', 'integrations.list_id', '=', 'platform_lists.id')
                                ->select('integrations.id as value', DB::raw("CONCAT(integrations.name , ' ( ', platform_lists.name, ' )') as label"))
                                ->get();

        return withSuccess(compact('lists', 'buyers', 'buyer_integrations', 'affiliates', 'affIds'));
    }

    /**
     * Retrieves performance data for the given request parameters.
     *
     * @param Request $request
     * @param ReportingService $reportingService
     * @return Response
     */
    public function getPerformanceData(Request $request, ReportingService $reportingService): Response
    {
        [$relationalConditions, $conditions] = $reportingService->formatFilters($request);

        $graphData = [];

        if (!empty($request->event_values)) {

            $performanceConfig = [
                'posted' => [
                    'label' => 'Posted',
                    'db_name' => 'COUNT(CASE WHEN lead_reports.is_posted = 1 THEN 1 END)',
                ],
                'accepted' => [
                    'label' => 'Accepted',
                    'db_name' => 'COUNT(CASE WHEN lead_reports.buyer_id IS NOT NULL and lead_reports.lead_id IS NOT NULL THEN 1 END)',
                ],
                'rejected' => [
                    'label' => 'Rejected',
                    'db_name' => 'COUNT(CASE WHEN lead_reports.buyer_id IS NULL and lead_reports.lead_id IS NOT NULL THEN 1 END)',
                ],
                'ar' => [
                    'label' => 'A/R',
                    'db_name' => 'COUNT(CASE WHEN lead_reports.buyer_id IS NOT NULL and lead_reports.lead_id IS NOT NULL THEN 1 END) / COUNT(CASE WHEN lead_reports.is_posted = 1 THEN 1 END) * 100',
                ],
                'ar_cpl' => [
                    'label' => 'A/R (CPL)',
                    'db_name' => 'COUNT(CASE WHEN lead_reports.sold_type = "CPL" THEN 1 END) / COUNT(CASE WHEN lead_reports.is_posted = 1 THEN 1 END) * 100',
                ],
                'revenue' => [
                    'label' => 'Revenue',
                    'db_name' => 'SUM(lead_reports.lead_revenue)',
                ],
                'profit' => [
                    'label' => 'Profit',
                    'db_name' => 'SUM(lead_reports.lead_profit)',
                ],
                'affiliate_payout' => [
                    'label' => 'Affiliate Payout',
                    'db_name' => 'SUM(lead_reports.affiliate_payout)',
                ],
                'affiliate_average_payout' => [
                    'label' => 'Affiliate Average Payout',
                    'db_name' => 'AVG(lead_reports.affiliate_payout)',
                ],
                'retained' => [
                    'label' => 'Retained',
                    'db_name' => 'COUNT(CASE WHEN lead_reports.is_retainer > 0 THEN 1 END)',
                ],
                'revenue_per_lead' => [
                    'label' => 'Revenue Per Lead',
                    'db_name' => 'AVG(lead_reports.lead_revenue)',
                ],
                'average_profit' => [
                    'label' => 'Average Profit',
                    'db_name' => 'AVG(lead_reports.lead_profit)',
                ],
            ];

            [$startDate, $endDate] = $this->formatStartEndDateWithTimezone($request->start_date, $request->end_date, $request->timezone, true);


            $diffDays = !empty($startDate) && !empty($endDate) ? $startDate->diffInDays($endDate) : 0;

            switch (true) {
                case ($diffDays <= 2):
                    $groupBy = 'HOUR(lead_reports.created_at)';
                    $formattedColumn = 'DATE_FORMAT(lead_reports.created_at, "%H:00, %W")';
                    break;
                case ($diffDays <= 7):
                    $groupBy = 'DATE(lead_reports.created_at)';
                    $formattedColumn = 'DATE_FORMAT(lead_reports.created_at, "%a, %d")';
                    break;
                case ($diffDays <= 31):
                    $groupBy = 'DAY(lead_reports.created_at)';
                    $formattedColumn = 'DATE_FORMAT(lead_reports.created_at, "%a, %d")';
                    break;
                case ($diffDays <= 60):
                    $groupBy = 'WEEK(lead_reports.created_at, 1)';
                    $formattedColumn = 'DATE_FORMAT(lead_reports.created_at, "%a, %d, %b")';
                    break;
                case ($diffDays <= 92):
                    $groupBy = 'MONTH(lead_reports.created_at)';
                        $formattedColumn = 'DATE_FORMAT(lead_reports.created_at, "%M, %Y")';
                    break;
                default:
                    $groupBy = 'MONTH(lead_reports.created_at)';
                    $formattedColumn = 'DATE_FORMAT(lead_reports.created_at, "%M, %Y")';
            }

            $performanceQueries[] = DB::raw($formattedColumn .' as day');

            foreach ($request->event_values as $event_num => $event) {

                //limit to 2 events
                if ($event_num < 2 && array_key_exists($event, $performanceConfig)) {
                    $performanceQueries[] = DB::raw($performanceConfig[$event]['db_name'] . ' as ' . $event);
                }

            }

            $performanceData = DB::table('lead_reports')
                                ->select($performanceQueries)
                                ->groupBy(DB::raw($groupBy))
                                ->limit(50)
                                ->when(! empty($startDate) && ! empty($endDate), function ($query) use ($startDate, $endDate) {
                                    return $query->whereBetween('lead_reports.created_at', [$startDate, $endDate]);
                                })
                                ->orderBy('lead_reports.created_at', 'asc')
                                ->leftJoin('platform_lists as pl', 'lead_reports.list_id', '=', 'pl.id')
                                ->leftJoin('buyers', 'lead_reports.buyer_id', '=', 'buyers.id')
                                ->leftJoin('integrations', 'lead_reports.buyer_integration_id', '=', 'integrations.id')
                                ->leftJoin('users as affiliate', 'lead_reports.affiliate_id', '=', 'affiliate.id')
                                ->when(! empty($relationalConditions), function ($query) use ($relationalConditions, $reportingService) {
                                    return $query->where(function ($query) use ($relationalConditions, $reportingService) {
                                        foreach ($relationalConditions as $conditionKey => $conditionGroup) {
                                            $query->where(function ($query2) use ($conditionGroup, $reportingService) {
                                                foreach ($conditionGroup as $index => $condition) {
                                                    $method = $reportingService->getConditionMethod($index);
                                                    $query2->$method($condition['column'], $condition['operator'], $condition['value']);
                                                }
                                            });
                                        }
                                    });
                                })
                                ->get();

            foreach ($request->event_values as $event_num => $event) {

                    //limit to 2 events
                    if ($event_num < 2 && array_key_exists($event, $performanceConfig)) {

                        $graphData[$event_num]['name'] = $performanceConfig[$request->event_values[$event_num]]['label'];
                        $graphData[$event_num]['data'] = collect($performanceData)->map(function ($item) use ($request, $event_num) {

                            $itemVal = $item->{$request->event_values[$event_num]};

                            return [
                                'x' => $item->day,
                                'y' => $itemVal,
                            ];
                        });

                    }
            }

        }

        return withSuccess($graphData);
    }
}
