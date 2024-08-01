<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Api\Lead\Requests\StoreLeadReportRequest;
use App\Http\Controllers\Api\Lead\Requests\UpdateLeadsRequest;
use App\Http\Controllers\Api\Lead\Resources\LeadInfoResource;
use App\Http\Controllers\Api\Lead\Resources\LeadReportResource;
use App\Http\Controllers\Api\Lead\Resources\LeadResource;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\LeadReport;
use App\Models\PlatformData;
use App\Models\PlatformList;
use App\Services\ExcelService;
use App\Services\LeadService;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    use CommonTrait;

    /**
     * Retrieves a paginated list of leads with associated buyer names.
     *
     * @param Request $request
     * @param LeadService $leadService
     * @param ExcelService $excelService
     *
     * @return Response | string | StreamedResponse
     */
    public function list(Request $request, LeadService $leadService, ExcelService $excelService): Response | string | StreamedResponse
    {
        [$startDate, $endDate] = $this->formatStartEndDateWithTimezone($request->start_date, $request->end_date, $request->timezone);
        $conditions = $leadService->formatFilters($request);
        $excelFilters = $leadService->formatExcelFilters($request);
        $perPage = empty($request->per_page) ? 10 : $request->per_page;

        $leadQuery = PlatformData::query()
                        ->select(
                            'platform_datas.id',
                            'platform_datas.datas',
                            'platform_datas.email',
                            'platform_datas.phone',
                            'platform_datas.buyer_integration_id',
                            'integrations.name as buyer_integration',
                            'platform_datas.buyer_id',
                            'buyers.name as buyer_name',
                            'platform_datas.affiliate_id',
                            'users.name as affiliate_name',
                            'platform_datas.lead_status',
                            'platform_lists.name as list_name',
                        )
                        ->leftJoin('integrations', 'platform_datas.buyer_integration_id', '=', 'integrations.id')
                        ->leftJoin('buyers', 'buyers.id', '=', 'platform_datas.buyer_id')
                        ->leftJoin('users', 'users.id', '=', 'platform_datas.affiliate_id')
                        ->leftJoin('platform_lists', 'platform_lists.id', '=', 'platform_datas.list_id')
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
                        ->when(! empty($startDate) && ! empty($endDate), function ($query) use ($startDate, $endDate) {
                            return $query->whereBetween('platform_datas.created_at', [$startDate, $endDate]);
                        })
                        ->when(! empty($request->search_txt), function ($query) use ($request) {

                            $searchText = strtolower($request->search_txt);

                            return $query->where(function ($query) use ($searchText) {
                                return $query->whereAny(
                                            [
                                                'platform_datas.email',
                                                'platform_datas.phone',
                                                'integrations.name',
                                            ],
                                            'like', "%{$searchText}%"
                                        )
                                        ->orWhereRaw('LOWER(datas) like ?', ["%{$searchText}%"]);
                                        // ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(datas, "$.first_name"))) LIKE ?', ["%{$searchText}%"])
                                    // ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(datas, "$.last_name"))) LIKE ?', ["%{$searchText}%"]);
                            });
                        })
                        ->when(! empty($excelFilters), function ($query) use ($excelFilters, $leadService) {
                            return $leadService->convertExcelFilterToSql($query, $excelFilters);
                        })
                        ->when(! empty($conditions), function ($query) use ($conditions, $leadService) {
                            return $leadService->convertFilterToSql($query, $conditions);
                        });

        if(! empty($request->is_export)){
            return $excelService->formatLeadExportData($leadQuery);
        }

        $leads = $leadQuery->paginate($perPage);

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
                        'integrations.name as buyer_integration',
                        'buyers.name as buyer_name',
                        'users.name as affiliate_name'
                    )
                    ->leftJoin('integrations', 'platform_datas.buyer_integration_id', '=', 'integrations.id')
                    ->leftJoin('buyers', 'buyers.id', '=', 'platform_datas.buyer_id')
                    ->leftJoin('users', 'users.id', '=', 'platform_datas.affiliate_id')
                    ->when(! empty($request->is_all), function($query){
                        return $query->addSelect(
                            'platform_datas.buyer_id',
                            'platform_datas.buyer_integration_id',
                            'platform_datas.list_id',
                            'platform_datas.affiliate_id',
                            'platform_datas.affid',
                            'platform_datas.lead_status',
                            'platform_datas.affm_source_id',
                            'platform_datas.affiliate_specs_id',
                            'platform_datas.sold_type'
                        );
                    })
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

    /**
     * Updates leads based on the provided UpdateLeadsRequest and LeadService.
     *
     * @param UpdateLeadsRequest $request
     * @param LeadService $leadService
     * @return Response
     */
    public function updateLeads(UpdateLeadsRequest $request, LeadService $leadService): Response
    {
        try {
            DB::beginTransaction();

            $leadService->formatAndUpdateLeads($request);

            DB::commit();
            return withSuccess('Leads updated successfully');
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollBack();

            return withError('Lead update failed');
        }
    }

    /**
     * Retrieves the lead reports for a specific lead.
     *
     * @param Request $request
     * @param int $leadId
     * @return Response
     */
    public function getLeadReports(Request $request, int $leadId): Response
    {
        $perPage = empty($request->per_page) ? 10 : $request->per_page;

        $reports = LeadReport::where('lead_id', $leadId)
                        ->select(
                            'id',
                            'is_retainer',
                            'is_paid',
                            'is_internal',
                            'is_posted',
                            'lead_revenue',
                            'affiliate_payout',
                            'lead_profit',
                            'affiliate_margin',
                            'profit_margin',
                            'created_at'
                        )
                        ->paginate($perPage);

        return withSuccessResourceList(LeadReportResource::collection($reports));
    }

    /**
     * Deletes a lead report.
     *
     * @param Request $request
     * @param int $reportId
     * @param LeadService $leadService
     * @return Response
     */
    public function deleteReport(Request $request, int $reportId, LeadService $leadService): Response
    {
        $report = LeadReport::find($reportId);
        if(empty($report)){
            return withError('Lead Report not found!');
        }

        try {

            DB::beginTransaction();
            $leadService->updateLeadStatus($report->lead_id, $reportId, false);
            $report->delete();
            $leadService->updateRevenuePayout($report->lead_id);
            DB::commit();

            return withSuccess('Lead Report deleted Successfully!');
        } catch (\Throwable $th) {
            info($th->getMessage());
            return withError('Lead Report Deletion Failed!');
        }
    }

    /**
     * Store a lead report.
     *
     * @param StoreLeadReportRequest $request
     * @param LeadService $leadService
     * @return Response
     */
    public function storeLeadReports(StoreLeadReportRequest $request, LeadService $leadService): Response
    {
        $formattedData = $leadService->formatReportRequest($request->validated());

        try {
            DB::beginTransaction();
            $report = LeadReport::create($formattedData);
            $leadService->updateReportData($report->id, $request);
            $leadService->updateLeadStatus($request->lead_id, $report->id, $request->is_retainer);
            $leadService->updateRevenuePayout($request->lead_id);
            DB::commit();

            return withSuccess(message: 'Lead Created Successfully');
        } catch (\Throwable $th) {
            info($th->getMessage());
            return withError('Lead Report Creation Failed');
        }
    }

    /**
     * Retrieves a single lead report based on the provided report ID.
     *
     * @param Request $request
     * @param int $reportId
     * @return Response
     */
    public function getSingleReports(Request $request, int $reportId): Response
    {
        $report = LeadReport::query()
                        ->select(
                            'id',
                            'is_retainer',
                            'is_paid',
                            'is_internal',
                            'is_posted',
                            'lead_revenue',
                            'affiliate_payout',
                            'lead_profit',
                            'affiliate_margin',
                            'profit_margin',
                            'sold_type',
                            'created_at'
                        )
                        ->find($reportId);

        if(empty($report)){
            return withError('Invalid Report Id Provided');
        }

        return withSuccess(new LeadReportResource($report));
    }

    /**
     * Updates a lead report based on the provided request and report ID.
     *
     * @param StoreLeadReportRequest $request
     * @param int $reportId
     * @param LeadService $leadService
     * @return Response
     */
    public function updateLeadReport(StoreLeadReportRequest $request, int $reportId, LeadService $leadService): Response
    {
        $report = LeadReport::find($reportId);
        if(empty($report)){
            return withError('Invalid Report Id Provided');
        }

        $formattedData = $leadService->formatReportRequest($request->validated());

        try {

            DB::beginTransaction();
            $report->update($formattedData);
            $leadService->updateReportData($report->id, $request);
            $leadService->updateLeadStatus($request->lead_id, $report->id, $request->is_retainer);
            $leadService->updateRevenuePayout($request->lead_id);
            DB::commit();

            return withSuccess(message: 'Lead Report Updated Successfully!');
        } catch (\Throwable $th) {
            info($th->getMessage());
            return withError('Lead Report Update Failed!');
        }
    }

    /**
     * Retrieves a list of buyer integrations based on the search text provided in the request.
     *
     * @param Request $request
     * @return Response
     */
    public function getBuyerIntegrations(Request $request): Response
    {
        $integrations = Integration::query()
                            ->select('id as value', 'name as label')
                            ->when(! empty($request->search_txt), function ($query) use ($request) {
                                return $query->where('name', 'like', "%{$request->search_txt}%");
                            })
                            ->limit(50)
                            ->get();

        return withSuccess($integrations);
    }
}
