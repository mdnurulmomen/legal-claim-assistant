<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Api\Lead\Resources\LeadInfoResource;
use App\Http\Controllers\Api\Lead\Resources\LeadResource;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\LeadReport;
use App\Models\PlatformData;
use App\Models\PlatformList;
use App\Services\LeadService;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    use CommonTrait;

    /**
     * Retrieves a paginated list of leads with associated buyer names.
     *
     * @param Request $request
     * @return Response
     */
    public function list(Request $request, LeadService $leadService): Response
    {
        [$startDate, $endDate] = $this->formatStartEndDateWithTimezone($request->start_date, $request->end_date, $request->timezone);
        $conditions = $leadService->formatFilters($request);

        $leads = PlatformData::query()
                    ->select(
                        'platform_datas.id',
                        'platform_datas.datas',
                        'platform_datas.email',
                        'platform_datas.phone',
                        'integrations.name as buyer_name'
                    )
                    ->leftJoin('integrations', 'platform_datas.buyer_integration_id', '=', 'integrations.id')
                    ->when(! empty($startDate) && ! empty($endDate), function ($query) use ($startDate, $endDate) {
                        return $query->whereBetween('platform_datas.created_at', [$startDate, $endDate]);
                    })
                    ->when(! empty($request->search_txt), function ($query) use ($request) {
                        return $query->where(function ($query) use ($request) {
                            return $query->whereAny(
                                    [
                                        'platform_datas.email',
                                        'platform_datas.phone',
                                        'integrations.name'
                                    ],
                                    'like',
                                    '%' . $request->search_txt . '%');
                                })
                                ->orWhere('platform_datas.datas->first_name', 'like', '%' . $request->search_txt . '%')
                                ->orWhere('platform_datas.datas->last_name', 'like', '%' . $request->search_txt . '%');
                    })
                    ->when(! empty($conditions), function ($query) use ($conditions, $leadService) {
                        return $query->where(function ($query) use ($conditions, $leadService) {
                            foreach ($conditions as $conditionKey => $conditionGroup) {

                                $method = $leadService->getConditionMethod($conditionKey);

                                $query->$method(function ($query2) use ($conditionGroup, $leadService) {

                                    foreach ($conditionGroup as $index => $condition) {

                                        $type = $leadService->getConditionType($condition['operator']);
                                        $method2 = $leadService->getConditionMethod($index, $type);

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
                    ->paginate($request->input('limit', 10));

        return withSuccessResourceList(LeadResource::collection($leads));
    }

    /**
     * Retrieves lead headers from the platform list.
     *
     * @param Request $request
     * @return Response
     */
    public function getLeadHeaders(Request $request, LeadService $leadService): Response
    {
        $platformDataColumns = PlatformList::query()
                                ->whereNotNull('lead_headers')
                                ->pluck('lead_headers')
                                ->flatten()
                                ->unique()
                                ->values();

        $platformDataColumns = $leadService->formatHeaders($platformDataColumns);

        $integrations = Integration::query()
                            ->select('buyer_unique_id', 'buyer_headers')
                            ->whereNotNull('buyer_headers')
                            ->get();


        return withSuccess([
            'platformDataColumns' => $platformDataColumns,
            'integrations' => $integrations,
            'defaultFields' => $leadService->getSortFields()
        ]);
    }

    /**
     * Retrieves the information of a specific lead.
     *
     * @param Request $request
     * @param int $leadId
     * @return Response
     */
    public function getLeadInfo(Request $request, int $leadId): Response
    {
        $leads = PlatformData::query()
                    ->select(
                        'platform_datas.id',
                        'platform_datas.datas',
                        'platform_datas.email',
                        'platform_datas.phone',
                        'integrations.name as buyer_name'
                    )
                    ->leftJoin('integrations', 'platform_datas.buyer_integration_id', '=', 'integrations.id')
                    ->addSelect([
                        'revenue' => LeadReport::select(DB::raw('sum(lead_reports.lead_revenue)'))
                                        ->whereColumn('lead_reports.lead_id', 'platform_datas.id')
                                        ->limit(1),

                        'profit' => LeadReport::select(DB::raw('sum(lead_reports.lead_profit)'))
                                        ->whereColumn('lead_reports.lead_id', 'platform_datas.id')
                                        ->limit(1),

                        'affiliate_payout' => LeadReport::select(DB::raw('sum(lead_reports.affiliate_payout)'))
                                                ->whereColumn('lead_reports.lead_id', 'platform_datas.id')
                                                ->limit(1),

                        'affiliate_margin' => LeadReport::select(DB::raw('sum(lead_reports.affiliate_margin)'))
                                                ->whereColumn('lead_reports.lead_id', 'platform_datas.id')
                                                ->limit(1)
                    ])
                    ->find($leadId);

        if(empty($leads)) {
            return withError('Lead not found');
        }

        return withSuccess(new LeadInfoResource($leads));
    }
}
