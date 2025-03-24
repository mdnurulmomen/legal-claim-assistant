<?php

namespace App\Http\Controllers\Api\Test;

use App\Helpers\Utility;
use App\Http\Controllers\Api\Test\Resources\TestResource;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\LeadReport;
use App\Models\PlatformData;
use App\Models\PlatformDataItem;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{

    public function updateIntegration(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', -1);

        $integrations = $request->integrations;

        $leadIdKeys = [
            'leadId', 'LeadId', 'lead_id', 'leadid', 'id', 'request_identifier',
            'confirmation_id', 'successLeadid', 'unique_id', 'TransactionId',
            'intakeId', 'ping_leadToken', 'bid_id', 'transaction_id'
        ];

        $responsePatterns = [
            ["message", "status", "result"],
            ["message", "status", "error"],
            ["message", "status", "errors"],
            ["default_response"],
            ["status", "response"],
            ["message", "error", "success"],
            ["status", "message", "data"],
            ["f"],
            ["Status"]
        ];

        // Process integrations
        $formattedData = collect($integrations)
            ->map(function ($item) use ($leadIdKeys, $responsePatterns) {
                $item['buyer_headers'] = json_decode($item['buyer_headers'], true);

                // Assign `lead_id_key` if missing
                if (empty($item['lead_id_key'])) {
                    $item['lead_id_key'] = collect($leadIdKeys)->first(fn($key) => in_array($key, $item['buyer_headers']));
                }

                // Check if buyer_headers match response patterns
                $sortedBuyerHeaders = $item['buyer_headers'];
                sort($sortedBuyerHeaders);

                $item['is_matched'] = collect($responsePatterns)->contains(function ($pattern) use ($sortedBuyerHeaders) {
                                            sort($pattern);
                                            return empty(array_diff($sortedBuyerHeaders, $pattern));
                                        });

                return $item;
            })
            ->filter(fn($item) => !empty($item['lead_id_key'])) // Ensure `lead_id_key` exists
            ->map(function ($item) {
                unset($item['is_matched'], $item['created_at'], $item['updated_at']);
                $item['buyer_headers'] = json_encode($item['buyer_headers']);
                return $item;
            })
            ->all();

        $upsert = Integration::upsert(
            $formattedData,
            ['id'],
            ['lead_id_key']
        );

        return withSuccess([
            'upsert' => $upsert,
            'count' => count($formattedData)
        ]);
    }

    public function updateDataItem(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', -1);

        $now = now();
        $limit = ! empty($request->limit) ? $request->limit : 50000;
        $chunkSize = ! empty($request->chunk_size) ? $request->chunk_size : 10000;

        $dataItem = PlatformDataItem::orderBy('platform_data_id')->first();

        $chunks = PlatformData::query()
                    ->whereNotNull('platform_datas.buyer_integration_id')
                    ->whereNotNull('integrations.lead_id_key')
                    ->leftJoin('integrations', 'platform_datas.buyer_integration_id', '=', 'integrations.id')
                    ->select([
                        'platform_datas.id',
                        'integrations.lead_id_key',
                        DB::raw("JSON_UNQUOTE(
                            JSON_EXTRACT(
                                platform_datas.datas,
                                CONCAT('$.', integrations.buyer_unique_id, '_', integrations.lead_id_key)
                            )
                        ) as lead_id")
                    ])
                    ->latest('platform_datas.id')
                    ->when(! empty($dataItem), function($query) use ($dataItem) {
                        return $query->where('platform_datas.id', '<', $dataItem->platform_data_id);
                    })
                    ->limit($limit)
                    ->get()
                    ->chunk($chunkSize);

                    foreach ($chunks as $key => $chunk) {

                        $leads = $chunk->values()
                                ->reject(fn ($item) => in_array($item->lead_id, [null, "null", "", "0", 0, false, " "], true))
                                ->map(function($item) use ($now) {
                                    return [
                                        'platform_data_id' => $item->id,
                                        'field' => $item->lead_id_key,
                                        'value' => $item->lead_id,
                                        'key_type' => 'lead_id',
                                        'created_at' => $now,
                                        'updated_at' => $now
                                    ];
                                })
                                ->values()
                                ->all();

                        if(empty($leads)) continue;

                        PlatformDataItem::insert($leads);
                    }

        return withSuccess(message: 'Data items updated successfully');
    }

    public function updateMissingRetainers(Request $request)
    {
        $formattedLeads = [];
        $formattedReports = [];
        $newReports = [];

        $leads = PlatformData::query()
                    // ->select(['id', 'phone', 'email', 'retained_date', 'lead_status', 'created_at', 'updated_at', 'revenue', 'payout'])
                    ->select([
                        'id',
                        'affiliate_id',
                        'list_id',
                        'buyer_id',
                        'affid',
                        'buyer_integration_id',
                        'affiliate_specs_id',
                        'affm_source_id',
                        'revenue',
                        'payout',
                        'sold_type',
                        'retained_date',
                        'lead_status',
                        'created_at',
                        'updated_at',
                        'revenue',
                        'payout'
                    ])
                    ->whereNull('retained_date')
                    ->where('lead_status', 'Retained')
                    ->when(! empty($request->updated_at), function($query) use ($request) {
                        return $query->whereDate('updated_at', $request->updated_at);
                    })
                    ->with('leadReports')
                    ->orderByDesc('id')
                    ->lazyById(500)
                    ->each(function($lead) use (&$formattedLeads, &$formattedReports, &$newReports) {
                        $formattedLeads[] = $this->formatLead($lead, $formattedReports, $newReports);
                    });

        // try {
        //     DB::beginTransaction();
        //         PlatformData::upsert(
        //             $formattedLeads,
        //             ['id'],
        //             ['retained_date', 'lead_status', 'is_retainer']
        //         );

        //         LeadReport::upsert(
        //             $formattedReports,
        //             ['id'],
        //             ['is_retainer']
        //         );

        //         LeadReport::insert($newReports);

        //     DB::commit();
        // } catch (\Throwable $th) {
        //     DB::rollBack();
        //     return withError('Lead Filled Fields Update Failed.' . $th->getMessage());
        // }

        return withSuccess([
            'formatted_leads' => $formattedLeads,
            'formatted_reports' => $formattedReports,
            'new_reports' => $newReports,
            'total_leads' => $leads->count(),
            // 'leads' => $leads
        ]);
    }

    private function formatLead($lead, &$formattedReports, &$newReports)
    {
        $newLead = [
            'id' => $lead->id,
            'lead_status' => $lead->lead_status,
            'retained_date' => $lead->retained_date,
            'is_retainer' => $lead->is_retainer
        ];

        if($lead->revenue == "0.00") {
            $newLead['lead_status'] = 'Pending';
            $newLead['retained_date'] = null;
            $newLead['is_retainer'] = 0;
        } else {
            $newLead['lead_status'] = 'Retained';
            $newLead['retained_date'] = $lead->updated_at;
            $newLead['is_retainer'] = 1;

            if(
                $lead->leadReports->isNotEmpty()
                && $lead->leadReports->doesntContain(function ($item) {
                    return $item['is_retainer'] > 0;
                })
            ) {
                $lastReport = $lead->leadReports->last();

                $formattedReports[] = [
                    'id' => $lastReport['id'],
                    'is_retainer' => 1
                ];
            }

            if($lead->leadReports->isEmpty()) {
                $newReports[] = $this->getFormData($lead);
            }
        }

        return $newLead;
    }

    public function getFormData($lead): array
    {
        $newDate = now();
        $revenue = (float) $lead->revenue;
        $payout = (float) $lead->payout;

        $formData = [
            'lead_id' => $lead['id'] ?? null,
            'affiliate_id' => $lead['affiliate_id'] ?? null,
            'list_id' => $lead['list_id'] ?? null,
            'buyer_id' => $lead['buyer_id'] ?? null,
            'affid' => $lead['affid'] ?? null,
            'buyer_integration_id' => $lead['buyer_integration_id'] ?? null,
            'affiliate_specs_id' => $lead['affiliate_specs_id'] ?? null,
            'affm_source_id' => $lead['affm_source_id'] ?? null,
            'is_retainer' => 1,
            'lead_revenue' => $revenue,
            'affiliate_payout' => $payout,
            'lead_profit' => 0,
            'affiliate_margin' => 0,
            'profit_margin' => 0,
            'sold_type' => $lead['sold_type'] ?? null,
            'created_at' => $lead['retained_date'] ?? $newDate,
            'updated_at' => $lead['retained_date'] ?? $newDate
        ];

        $reportData = $this->calculateRevenuePayout((float) $formData['lead_revenue'], (float) $formData['affiliate_payout']);

        return array_merge($formData, $reportData);
    }

    public function calculateRevenuePayout( float | int $revenue = 0, float | int $affiliatePayout): array
    {
        $profit = $revenue - $affiliatePayout;
        $affiliateMargin = $revenue ? (($affiliatePayout / $revenue) * 100) : 0;
        $profitMargin = $revenue ? (($profit / $revenue) * 100) : 0;

        return [
            'lead_profit' => $profit,
            'affiliate_margin' => $affiliateMargin,
            'profit_margin' => $profitMargin
        ];
    }
}
